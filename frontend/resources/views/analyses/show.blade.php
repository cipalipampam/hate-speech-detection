@extends('layouts.app')

@section('title', '§ 02.1 Dosir Analisis: ' . ($analysis->title ?? 'Detail'))

@section('breadcrumb')
<a href="{{ route('analyses.index') }}" style="color:var(--color-text-muted);text-decoration:none;">§ 02.0 RIWAYAT</a>
<span>/</span>
<span style="color:#0A0A0A;">DOSIR #{{ str_pad($analysis->id, 4, '0', STR_PAD_LEFT) }}</span>
@endsection

@section('content')

{{-- Safe JSON Island for initial server state --}}
<script id="analysis-server-data" type="application/json">
    {!! json_encode([
        'analysisId'      => $analysis->id,
        'status'          => $analysis->status,
        'stats'           => $analysis->statistic,
        'keywords'        => $analysis->keywords ?? [],
        'exportUrl'       => route('analyses.export', $analysis->id),
        'execTime'        => $analysis->execution_time_seconds,
        'pipelineStep'    => $analysis->status === 'completed' ? 4 : ($analysis->pipeline_step ?? 1),
        'pipelineMessage' => ($analysis->pipeline_message && stripos($analysis->pipeline_message, 'polling') === false && stripos($analysis->pipeline_message, 'asinkron') === false)
            ? $analysis->pipeline_message
            : ($analysis->status === 'completed' ? 'Seluruh sekuensial pipeline AI telah selesai dieksekusi.' : 'Menghubungkan ke antrean worker pemrosesan...'),
        'posts'           => $posts->items(),
        'selectedPost'    => $posts->items()[0] ?? null,
        'pagination'      => [
            'current_page'  => $posts->currentPage(),
            'last_page'     => $posts->lastPage(),
            'total'         => $posts->total(),
            'from'          => $posts->firstItem() ?? 0,
            'to'            => $posts->lastItem() ?? 0,
            'prev_page_url' => $posts->previousPageUrl(),
            'next_page_url' => $posts->nextPageUrl(),
        ],
        'filters'         => [
            'search'     => request('search', ''),
            'label_lvl1' => request('label_lvl1', 'all'),
            'label_lvl2' => request('label_lvl2', 'all'),
            'platform'   => request('platform', 'all'),
        ]
    ]) !!}
</script>

<script>
// Chart instances kept outside Alpine reactive proxy to avoid reactive loop and performance degradation
let sentimentChartInstance = null;
let level2ChartInstance = null;

function analysisDetail() {
    let initial = {
        analysisId: {{ $analysis->id }},
        status: '{{ $analysis->status }}',
        stats: null,
        keywords: [],
        exportUrl: '',
        execTime: null,
        pipelineStep: 1,
        pipelineMessage: 'Mengecek alur pipeline pada worker...',
        posts: [],
        selectedPost: null,
        pagination: {},
        filters: { search: '', label_lvl1: 'all', label_lvl2: 'all', platform: 'all' }
    };
    try {
        const el = document.getElementById('analysis-server-data');
        if (el && el.textContent) {
            initial = JSON.parse(el.textContent);
        }
    } catch(e) {
        console.error('Failed to parse analysis server data:', e);
    }

    return {
        analysisId:      initial.analysisId,
        status:          initial.status,
        stats:           initial.stats,
        keywords:        initial.keywords,
        exportUrl:       initial.exportUrl,
        execTime:        initial.execTime,
        pipelineStep:    initial.pipelineStep || 1,
        pipelineMessage: initial.pipelineMessage || 'Mengecek alur pipeline pada worker...',
        pollTimer:       null,
        showDetail:      (initial.status === 'completed'),
        chartsRendered:  false,

        // Table & Filter state
        posts:         initial.posts || [],
        selectedPost:  initial.selectedPost || null,
        pagination:    initial.pagination || {},
        filters:       initial.filters || { search: '', label_lvl1: 'all', label_lvl2: 'all', platform: 'all' },
        loadingPosts:  false,

        init() {
            if (this.status === 'running' || this.status === 'queued') {
                this.startPolling();
            } else if (this.status === 'completed') {
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
                if (this.showDetail) {
                    this.$nextTick(() => {
                        this.renderCharts();
                    });
                }
            }
        },

        startPolling() {
            // Guard: Jangan jalankan polling jika sudah selesai atau gagal
            if (this.status !== 'running' && this.status !== 'queued') {
                return;
            }
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }

            this.pollTimer = setInterval(async () => {
                if (this.status === 'completed' || this.status === 'failed') {
                    if (this.pollTimer) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                    return;
                }

                try {
                    const res = await fetch(`{{ route('analyses.show', $analysis->id) }}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    const data = await res.json();
                    if (data && data.analysis) {
                        this.status = data.analysis.status;
                        this.execTime = data.analysis.execution_time_seconds;
                        if (data.analysis.pipeline_step) {
                            this.pipelineStep = data.analysis.pipeline_step;
                        }
                        if (data.analysis.pipeline_message) {
                            this.pipelineMessage = data.analysis.pipeline_message;
                        }
                        if (data.statistic) {
                            this.stats = data.statistic;
                        }
                        if (data.posts) {
                            this.posts = data.posts.data || [];
                            this.updatePagination(data.posts);
                            if (!this.selectedPost && this.posts.length > 0) {
                                this.selectedPost = this.posts[0];
                            }
                        }

                        if (this.status === 'completed') {
                            if (this.pollTimer) {
                                clearInterval(this.pollTimer);
                                this.pollTimer = null;
                            }
                            this.pipelineStep = 4;
                            this.pipelineMessage = 'Pipeline AI selesai diproses. Seluruh postingan telah berhasil diunduh dan diklasifikasikan.';
                        } else if (this.status === 'failed') {
                            if (this.pollTimer) {
                                clearInterval(this.pollTimer);
                                this.pollTimer = null;
                            }
                        }
                    }
                } catch (e) {
                    console.error('Polling error:', e);
                }
            }, 3500);
        },

        async fetchPosts(page = 1) {
            this.loadingPosts = true;
            try {
                const query = new URLSearchParams({
                    page:       page,
                    search:     this.filters.search || '',
                    label_lvl1: this.filters.label_lvl1 || 'all',
                    label_lvl2: this.filters.label_lvl2 || 'all',
                    platform:   this.filters.platform || 'all',
                });

                const res = await fetch(`{{ route('analyses.show', $analysis->id) }}?${query.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });
                const data = await res.json();
                if (data && data.posts) {
                    this.posts = data.posts.data || [];
                    this.updatePagination(data.posts);
                    if (this.posts.length > 0) {
                        this.selectedPost = this.posts[0];
                    }
                }
            } catch (e) {
                console.error('Failed to fetch posts:', e);
            } finally {
                this.loadingPosts = false;
            }
        },

        resetFilters() {
            this.filters.search = '';
            this.filters.label_lvl1 = 'all';
            this.filters.label_lvl2 = 'all';
            this.filters.platform = 'all';
            this.fetchPosts(1);
        },

        updatePagination(paginator) {
            this.pagination = {
                current_page:  paginator.current_page,
                last_page:     paginator.last_page,
                total:         paginator.total,
                from:          paginator.from,
                to:            paginator.to,
                prev_page_url: paginator.prev_page_url,
                next_page_url: paginator.next_page_url,
            };
        },

        selectNext() {
            if (!this.posts || this.posts.length === 0) return;
            const currentIdx = this.posts.findIndex(p => p.id === this.selectedPost?.id);
            if (currentIdx >= 0 && currentIdx < this.posts.length - 1) {
                this.selectedPost = this.posts[currentIdx + 1];
            }
        },

        selectPrev() {
            if (!this.posts || this.posts.length === 0) return;
            const currentIdx = this.posts.findIndex(p => p.id === this.selectedPost?.id);
            if (currentIdx > 0) {
                this.selectedPost = this.posts[currentIdx - 1];
            }
        },

        renderXaiHighlighted(text, labelLvl1) {
            if (!text) return '—';
            const toxicWords = [
                'anjing', 'babi', 'tolol', 'goblok', 'bangsat', 'bajingan', 'penipu',
                'rezim', 'zalim', 'korup', 'antek', 'asing', 'hancurkan', 'racun',
                'bunuh', 'habisi', 'pecat', 'biadab', 'komunis', 'pki', 'hoaks', 'hoax'
            ];

            const words = text.split(/(\s+)/);
            return words.map(w => {
                const clean = w.toLowerCase().replace(/[^a-z0-9]/g, '');
                if (labelLvl1 === 'hate_speech' && toxicWords.includes(clean)) {
                    return `<span class="xai-toxic">${w}</span>`;
                }
                return w;
            }).join('');
        },

        numberFormat(num) {
            return new Intl.NumberFormat('id-ID').format(num);
        },

        formatLabel(label) {
            const map = {
                'delegitimasi_institusi': 'Delegitimasi Institusi',
                'dehumanisasi':           'Dehumanisasi',
                'ajakan_kekerasan':       'Ajakan Kekerasan',
                'hoaks_pemicu_kebencian': 'Hoaks Pemicu Kebencian',
                'hoax_pemicu_kebencian':  'Hoaks Pemicu Kebencian',
                'kutukan_agama_personal': 'Kutukan Agama & Personal',
                'tidak_relevan':          'Tidak Relevan',
            };
            return map[label] || label || '—';
        },

        renderCharts() {
            if (typeof ApexCharts === 'undefined') return;

            // Guard: hanya render sekali untuk analisis yang sudah selesai
            if (this.chartsRendered) return;
            this.chartsRendered = true;

            const hateCount = this.stats?.hate_speech_count || 0;
            const nonHateCount = this.stats?.non_hate_speech_count || 0;

            const elSentiment = document.querySelector('#session-sentiment-chart');
            if (elSentiment && (hateCount > 0 || nonHateCount > 0)) {
                // Destroy instance lama (di luar proxy Alpine) sebelum membuat baru
                if (sentimentChartInstance) {
                    sentimentChartInstance.destroy();
                    sentimentChartInstance = null;
                }
                sentimentChartInstance = new ApexCharts(elSentiment, {
                    chart: { type: 'donut', height: 210, fontFamily: 'JetBrains Mono, monospace', animations: { enabled: true, speed: 350 } },
                    series: [hateCount, nonHateCount],
                    labels: ['Hate Speech', 'Non-Hate'],
                    colors: ['#FACC15', '#002FA7'],
                    stroke: { width: 2, colors: ['#0A0A0A'] },
                    dataLabels: { enabled: false },
                    legend: { position: 'bottom', fontSize: '11px', fontFamily: 'JetBrains Mono, monospace' },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '68%',
                                labels: {
                                    show: true,
                                    total: {
                                        show: true,
                                        label: 'TOTAL',
                                        formatter: () => (hateCount + nonHateCount)
                                    }
                                }
                            }
                        }
                    }
                });
                sentimentChartInstance.render();
            }

            const lvl2Map = this.stats?.level2_breakdown || {};
            const categories = [
                'Delegitimasi Institusi',
                'Dehumanisasi',
                'Ajakan Kekerasan',
                'Hoaks Pemicu Kebencian',
                'Kutukan Agama & Personal',
                'Tidak Relevan'
            ];
            const dataCounts = [
                lvl2Map['delegitimasi_institusi']?.count || 0,
                lvl2Map['dehumanisasi']?.count || 0,
                lvl2Map['ajakan_kekerasan']?.count || 0,
                (lvl2Map['hoaks_pemicu_kebencian']?.count || lvl2Map['hoax_pemicu_kebencian']?.count || 0),
                lvl2Map['kutukan_agama_personal']?.count || 0,
                lvl2Map['tidak_relevan']?.count || 0,
            ];

            const elLvl2 = document.querySelector('#session-level2-chart');
            if (elLvl2) {
                if (level2ChartInstance) {
                    level2ChartInstance.destroy();
                    level2ChartInstance = null;
                }
                level2ChartInstance = new ApexCharts(elLvl2, {
                    chart: { type: 'bar', height: 210, fontFamily: 'JetBrains Mono, monospace', toolbar: { show: false }, animations: { enabled: true, speed: 350 } },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 0,
                            barHeight: '55%',
                        }
                    },
                    colors: ['#0A0A0A'],
                    stroke: { width: 1, colors: ['#002FA7'] },
                    series: [{ name: 'Postingan', data: dataCounts }],
                    xaxis: { categories: categories, labels: { style: { fontSize: '10px' } } },
                    legend: { show: false },
                    dataLabels: { enabled: true, style: { fontSize: '11px', fontFamily: 'JetBrains Mono, monospace' } },
                    grid: { strokeDashArray: 0, borderColor: '#E3E2DC' }
                });
                level2ChartInstance.render();
            }
        }
    };
}
</script>

<div x-data="analysisDetail()" x-init="init()"
     @keydown.window.j="selectNext()"
     @keydown.window.k="selectPrev()">

    {{-- ── Monograph Dossier Header ── --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1.25rem;margin-bottom:1.75rem;display:flex;align-items:flex-start;justify-content:space-between;gap:1.25rem;flex-wrap:wrap;">
        <div>
            <div style="display:flex;align-items:center;gap:0.625rem;margin-bottom:0.375rem;flex-wrap:wrap;">
                <span class="badge badge-black">DOSIR #{{ str_pad($analysis->id, 4, '0', STR_PAD_LEFT) }}</span>
                <template x-if="status === 'completed'">
                    <span class="badge badge-safe">SELESAI (VERIFIED)</span>
                </template>
                <template x-if="status === 'running'">
                    <span class="badge badge-hate" style="animation:telemetry-pulse 1.5s infinite;">PIPELINE PROSES</span>
                </template>
                <template x-if="status === 'queued'">
                    <span class="badge badge-mono">DALAM ANTREAN</span>
                </template>
                <template x-if="status === 'failed'">
                    <span class="badge" style="background:#FEE2E2;color:#991B1B;border-color:#991B1B;">GAGAL DIEKSEKUSI</span>
                </template>
            </div>

            <h1 style="font-size:1.875rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0 0 0.375rem;line-height:1.15;">
                {{ $analysis->title }}
            </h1>

            <div style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;gap:1rem;flex-wrap:wrap;">
                <span>PLATFORM: <strong style="color:#0A0A0A;">{{ strtoupper($analysis->platform) }}</strong></span>
                <span>·</span>
                <span>MODE: <strong style="color:#0A0A0A;">{{ strtoupper($analysis->search_mode) }}</strong></span>
                <span>·</span>
                <span>KUOTA: <strong style="color:#0A0A0A;">{{ $analysis->max_links }} URL</strong></span>
                <span>·</span>
                <span>PENELITI: <strong style="color:#0A0A0A;">{{ $analysis->user->name ?? 'SYSTEM' }}</strong></span>
                <span>·</span>
                <span>WAKTU: <strong style="color:#0A0A0A;">{{ $analysis->created_at->format('Y-m-d H:i') }}</strong></span>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:0.625rem;flex-wrap:wrap;">
            <template x-if="status === 'completed'">
                <button type="button" @click="showDetail = !showDetail; if(showDetail) $nextTick(() => renderCharts())" class="btn btn-outline btn-sm" style="font-family:var(--font-mono);font-size:0.6875rem;">
                    <span x-text="showDetail ? '⟳ ALUR PIPELINE' : '→ BUKA MEJA RISET'"></span>
                </button>
            </template>
            <template x-if="status === 'completed'">
                <a :href="exportUrl" class="btn btn-outline btn-sm" style="gap:0.375rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>UNDUH DATASET (CSV)</span>
                </a>
            </template>
            <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-sm">
                <span>+ INVESTIGASI BARU</span>
            </a>
        </div>
    </div>

    {{-- Error Banner --}}
    @if($analysis->status === 'failed')
    <div style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;padding:1rem;margin-bottom:1.5rem;">
        <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin:0 0 0.25rem;">ERROR: PIPELINE GAGAL</p>
        <p style="font-size:0.875rem;font-weight:600;color:#0A0A0A;margin:0;">
            {{ $analysis->error_message ?? 'Koneksi ke backend FastAPI terputus atau Playwright mengalami kegagalan sesi. Periksa log server.' }}
        </p>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════
         STATE 1: PIPELINE PROGRESS & SUMMARY (Swiss Monograph Stepper)
    ════════════════════════════════════════════════════════════════════ --}}
    <div x-show="!showDetail || status === 'running' || status === 'queued'" x-cloak style="margin-bottom:2rem;">
        <div class="card" style="padding:1.5rem;border-left:6px solid #0A0A0A;box-shadow:3px 3px 0 #0A0A0A;">
            <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #0A0A0A;padding-bottom:1rem;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem;">
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:36px;height:36px;background:#0A0A0A;color:#FFFFFF;display:flex;align-items:center;justify-content:center;font-family:var(--font-mono);font-weight:900;font-size:1.125rem;">
                        <template x-if="status === 'completed'"><span>✓</span></template>
                        <template x-if="status !== 'completed'"><span>⟳</span></template>
                    </div>
                    <div>
                        <h2 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0;"
                            x-text="status === 'completed' ? 'Pipeline AI Berhasil Diselesaikan' : 'Pipeline AI Sedang Berjalan'"></h2>
                        <p style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);margin:2px 0 0;" x-text="pipelineMessage"></p>
                    </div>
                </div>
                <div>
                    <template x-if="status === 'completed'">
                        <span class="badge badge-safe">PIPELINE TUNTAS (100%)</span>
                    </template>
                    <template x-if="status === 'running'">
                        <span class="badge badge-mono">STATUS: AKTIF</span>
                    </template>
                    <template x-if="status === 'queued'">
                        <span class="badge badge-mono">DALAM ANTREAN</span>
                    </template>
                </div>
            </div>

            {{-- Sharp Dynamic Progress Track --}}
            <div class="progress-track-sharp" style="margin-bottom:1.5rem;background:var(--color-surface-2);border:1px solid #0A0A0A;height:8px;">
                <div class="progress-fill-sharp"
                     :style="'background:' + (status === 'completed' ? 'var(--color-primary)' : 'var(--color-danger)') + '; width:' + (status === 'completed' ? 100 : (pipelineStep === 4 ? 90 : (pipelineStep === 3 ? 70 : (pipelineStep === 2 ? 45 : 15)))) + '%; transition: width 0.4s ease;'"></div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1.25rem;">
                {{-- Parameter Kontrol --}}
                <div class="card-flat" style="padding:1rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;">
                    <div>
                        <span class="stat-block-label">PARAMETER KONTROL RISET</span>
                        <p style="font-family:var(--font-mono);font-size:0.8125rem;margin:0.5rem 0 0.25rem;">
                            TARGET: <strong>{{ strtoupper($analysis->platform) }}</strong> · BATAS: <strong>{{ $analysis->max_links }} POST</strong>
                        </p>
                        <div style="display:flex;flex-wrap:wrap;gap:0.25rem;margin-top:0.5rem;">
                            @foreach($analysis->keywords ?? [] as $kw)
                                <span class="badge badge-mono">#{{ $kw }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div style="border-top:1px solid var(--color-border-subtle);padding-top:0.5rem;display:flex;justify-content:space-between;align-items:center;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">
                        <span>DURASI KOMPUTASI:</span>
                        <strong style="color:#0A0A0A;" x-text="execTime ? Math.round(execTime) + ' DETIK' : 'BERJALAN...'"></strong>
                    </div>
                </div>

                {{-- Dynamic Sequential Pipeline Steps --}}
                <div style="display:flex;flex-direction:column;gap:0.375rem;">
                    <span class="stat-block-label" style="margin-bottom:0.15rem;">SEKUENSIAL ALUR PIPELINE</span>
                    
                    {{-- Step 1 --}}
                    <div :style="(pipelineStep > 1 || status === 'completed') ? 'padding:0.45rem 0.65rem;background:#FFFFFF;border:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : (pipelineStep === 1 ? 'padding:0.45rem 0.65rem;background:var(--color-danger);border:2px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : 'padding:0.45rem 0.65rem;background:var(--color-surface-2);border:1px solid var(--color-border-subtle);font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;align-items:center;justify-content:space-between;')">
                        <span x-text="(pipelineStep > 1 || status === 'completed') ? '✓ 01. Verifikasi Sesi' : (pipelineStep === 1 ? '▶ 01. Verifikasi Sesi' : '○ 01. Verifikasi Sesi')"></span>
                        <span class="badge badge-safe" style="font-size:0.5625rem;" x-show="pipelineStep > 1 || status === 'completed'">SELESAI</span>
                        <span class="badge badge-black" style="font-size:0.5625rem;" x-show="pipelineStep === 1 && status !== 'completed'">PROSES</span>
                    </div>

                    {{-- Step 2 --}}
                    <div :style="(pipelineStep > 2 || status === 'completed') ? 'padding:0.45rem 0.65rem;background:#FFFFFF;border:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : (pipelineStep === 2 ? 'padding:0.45rem 0.65rem;background:var(--color-danger);border:2px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : 'padding:0.45rem 0.65rem;background:var(--color-surface-2);border:1px solid var(--color-border-subtle);font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;align-items:center;justify-content:space-between;')">
                        <span x-text="(pipelineStep > 2 || status === 'completed') ? '✓ 02. Scraping Data Media Sosial' : (pipelineStep === 2 ? '▶ 02. Scraping Data Media Sosial' : '○ 02. Scraping Data Media Sosial')"></span>
                        <span class="badge badge-safe" style="font-size:0.5625rem;" x-show="pipelineStep > 2 || status === 'completed'">SELESAI</span>
                        <span class="badge badge-black" style="font-size:0.5625rem;" x-show="pipelineStep === 2 && status !== 'completed'">PROSES</span>
                    </div>

                    {{-- Step 3 --}}
                    <div :style="(pipelineStep > 3 || status === 'completed') ? 'padding:0.45rem 0.65rem;background:#FFFFFF;border:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : (pipelineStep === 3 ? 'padding:0.45rem 0.65rem;background:var(--color-danger);border:2px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : 'padding:0.45rem 0.65rem;background:var(--color-surface-2);border:1px solid var(--color-border-subtle);font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;align-items:center;justify-content:space-between;')">
                        <span x-text="(pipelineStep > 3 || status === 'completed') ? '✓ 03. Preprocessing' : (pipelineStep === 3 ? '▶ 03. Preprocessing' : '○ 03. Preprocessing')"></span>
                        <span class="badge badge-safe" style="font-size:0.5625rem;" x-show="pipelineStep > 3 || status === 'completed'">SELESAI</span>
                        <span class="badge badge-black" style="font-size:0.5625rem;" x-show="pipelineStep === 3 && status !== 'completed'">PROSES</span>
                    </div>

                    {{-- Step 4 --}}
                    <div :style="status === 'completed' ? 'padding:0.45rem 0.65rem;background:#FFFFFF;border:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : (pipelineStep === 4 ? 'padding:0.45rem 0.65rem;background:var(--color-danger);border:2px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : 'padding:0.45rem 0.65rem;background:var(--color-surface-2);border:1px solid var(--color-border-subtle);font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;align-items:center;justify-content:space-between;')">
                        <span x-text="status === 'completed' ? '✓ 04. Inferensi Hierarkis IndoBERT (L1 & L2)' : (pipelineStep === 4 ? '▶ 04. Inferensi Hierarkis IndoBERT (L1 & L2)' : '○ 04. Inferensi Hierarkis IndoBERT (L1 & L2)')"></span>
                        <span class="badge badge-safe" style="font-size:0.5625rem;" x-show="status === 'completed'">SELESAI</span>
                        <span class="badge badge-black" style="font-size:0.5625rem;" x-show="pipelineStep === 4 && status !== 'completed'">PROSES</span>
                    </div>
                </div>
            </div>

            {{-- Completion Summary & Explicit Action Button --}}
            <div x-show="status === 'completed'" style="background:var(--color-surface-2);border:1px solid #0A0A0A;border-top:2px solid #0A0A0A;padding:1.25rem;margin-top:1.5rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
                    <div>
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                            <span class="badge badge-safe">PROSES SELESAI 100%</span>
                            <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:#0A0A0A;">STATUS: DATA SIAP DITELAAH</span>
                        </div>
                        <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0;">
                            Ekstraksi scraper dan inferensi klasifikasi IndoBERT telah lengkap. Tekan tombol di bawah untuk membuka dosir data dan meja riset interaktif.
                        </p>
                    </div>
                    <div style="font-family:var(--font-mono);font-size:0.75rem;text-align:right;">
                        <span style="display:block;color:var(--color-text-muted);">TOTAL DATA DIANALISIS:</span>
                        <strong style="font-size:1.125rem;color:#0A0A0A;" x-text="numberFormat(stats?.total_data || 0) + ' POST'"></strong>
                    </div>
                </div>

                <button type="button"
                        @click="showDetail = true; $nextTick(() => renderCharts())"
                        class="btn btn-primary btn-lg"
                        style="width:100%;font-weight:900;letter-spacing:0.04em;height:46px;box-shadow:3px 3px 0 #0A0A0A;display:flex;justify-content:center;">
                    <span>LIHAT DETAIL HASIL ANALISIS & MEJA RISET [→]</span>
                </button>
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         STATE 2: COMPLETED (Swiss Split-Pane Research Desk)
    ════════════════════════════════════════════════════════════════════ --}}
    <div x-show="showDetail && status === 'completed'" x-cloak style="display:flex;flex-direction:column;gap:1.5rem;">

        {{-- ── 5 Stat Blocks Summary ── --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));gap:0.875rem;margin-bottom:2rem;">
            <div class="stat-block">
                <span class="stat-block-label">TOTAL DATA (N)</span>
                <span class="stat-block-val" x-text="numberFormat(stats?.total_data || 0)"></span>
                <div class="stat-block-meta">[OPINI BERHASIL DIUNDUH]</div>
            </div>

            <div class="stat-block" style="background:var(--color-danger-bg);border-top:3px solid var(--color-danger);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="stat-block-label" style="color:#0A0A0A;">UJARAN KEBENCIAN</span>
                    <span class="badge badge-hate" style="font-size:0.6rem;">FLAG</span>
                </div>
                <span class="stat-block-val" style="color:#0A0A0A;" x-text="numberFormat(stats?.hate_speech_count || 0)"></span>
                <div class="stat-block-meta" style="border-color:#0A0A0A;color:#0A0A0A;">[LEVEL 1: TOKSIK]</div>
            </div>

            <div class="stat-block" style="background:var(--color-primary-bg);border-top:3px solid var(--color-primary);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="stat-block-label" style="color:var(--color-primary);">KONTEN AMAN</span>
                    <span class="badge badge-safe" style="font-size:0.6rem;">SAFE</span>
                </div>
                <span class="stat-block-val" style="color:var(--color-primary);" x-text="numberFormat(stats?.non_hate_speech_count || 0)"></span>
                <div class="stat-block-meta" style="border-color:var(--color-primary);color:var(--color-primary);">[LEVEL 1: NON-HATE]</div>
            </div>

            <div class="stat-block">
                <span class="stat-block-label">RASIO TOKSISITAS</span>
                <span class="stat-block-val" x-text="(stats?.hate_speech_pct || 0) + '%'"></span>
                <div class="stat-block-meta">[HATE PERCENTAGE]</div>
            </div>

            <div class="stat-block">
                <span class="stat-block-label">WAKTU EKSEKUSI</span>
                <span class="stat-block-val" x-text="execTime ? Math.round(execTime) + 's' : '—'"></span>
                <div class="stat-block-meta">[DURASI KOMPUTASI]</div>
            </div>
        </div>

        {{-- ── Dual Charts (Sentimen & Kategori Level 2) ── --}}
        <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:1.25rem;margin-bottom:2.5rem;" class="charts-row">
            <div class="card" style="padding:1.25rem;">
                <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.5rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;">
                    <span class="stat-block-label">DISTRIBUSI SENTIMEN L1</span>
                    <span class="badge badge-mono">SESI AKTIF</span>
                </div>
                <div id="session-sentiment-chart"></div>
            </div>

            <div class="card" style="padding:1.25rem;">
                <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.5rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;">
                    <span class="stat-block-label">TAKSONOMI 6 SUB-KATEGORI LEVEL 2</span>
                    <span class="badge badge-mono">INDOBERT-V2</span>
                </div>
                <div id="session-level2-chart"></div>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════
             THE SPLIT-PANE RESEARCH DESK (Stream List + Sticky Inspector)
        ════════════════════════════════════════════════════════════════ --}}
        <div style="display:grid;grid-template-columns:1.8fr 1.2fr;gap:1.25rem;align-items:flex-start;" class="split-pane-grid">

            {{-- ── LEFT PANE: Filterable Postings Stream (70%) ── --}}
            <div style="display:flex;flex-direction:column;gap:0.75rem;">

                {{-- Filter Toolbar --}}
                <div class="card" style="padding:0.75rem 1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                        <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;">
                            STREAM POSTINGAN (<span x-text="pagination.total || 0"></span>)
                        </span>

                        <div style="display:flex;align-items:center;gap:0.375rem;flex-wrap:wrap;">
                            {{-- Search Input --}}
                            <input type="text"
                                   x-model.debounce.350ms="filters.search"
                                   @input="fetchPosts(1)"
                                   placeholder="Cari teks... (/)"
                                   class="input"
                                   style="width:140px;height:30px;font-size:0.75rem;padding:0.25rem 0.5rem;">

                            {{-- L1 Filter --}}
                            <select x-model="filters.label_lvl1" @change="fetchPosts(1)" class="input" style="width:auto;height:30px;font-size:0.75rem;padding:0 0.5rem;cursor:pointer;">
                                <option value="all">Semua L1</option>
                                <option value="hate_speech">Hate</option>
                                <option value="non_hate_speech">Safe</option>
                            </select>

                            {{-- L2 Filter --}}
                            <select x-model="filters.label_lvl2" @change="fetchPosts(1)" class="input" style="width:auto;height:30px;font-size:0.75rem;padding:0 0.5rem;cursor:pointer;">
                                <option value="all">Semua L2</option>
                                <option value="delegitimasi_institusi">Delegitimasi Institusi</option>
                                <option value="dehumanisasi">Dehumanisasi</option>
                                <option value="ajakan_kekerasan">Ajakan Kekerasan</option>
                                <option value="hoaks_pemicu_kebencian">Hoaks Pemicu Kebencian</option>
                                <option value="kutukan_agama_personal">Kutukan Agama & Personal</option>
                                <option value="tidak_relevan">Tidak Relevan</option>
                            </select>

                            {{-- Platform Filter --}}
                            <select x-model="filters.platform" @change="fetchPosts(1)" class="input" style="width:auto;height:30px;font-size:0.75rem;padding:0 0.5rem;cursor:pointer;">
                                <option value="all">Semua 𝕏/⊙</option>
                                <option value="X">𝕏 Twitter</option>
                                <option value="Threads">⊙ Threads</option>
                            </select>

                            <button type="button" x-show="filters.search || filters.label_lvl1 !== 'all' || filters.label_lvl2 !== 'all' || filters.platform !== 'all'"
                                    @click="resetFilters()" class="btn btn-ghost btn-sm" style="height:30px;padding:0 0.35rem;">✕</button>
                        </div>
                    </div>
                </div>

                {{-- Postings List --}}
                <div style="position:relative;display:flex;flex-direction:column;gap:0.625rem;">
                    {{-- Skeleton Loading Overlay --}}
                    <div x-show="loadingPosts" x-cloak style="position:absolute;inset:0;background:rgba(246,245,240,0.75);display:flex;align-items:center;justify-content:center;z-index:10;">
                        <span class="badge badge-black">MEMUAT KORPUS...</span>
                    </div>

                    <template x-if="posts.length === 0 && !loadingPosts">
                        <div class="card" style="padding:3rem 1rem;text-align:center;font-family:var(--font-mono);color:var(--color-text-muted);">
                            [TIDAK DITEMUKAN POSTINGAN SESUAI FILTER]
                        </div>
                    </template>

                    <template x-for="(post, index) in posts" :key="post.id">
                        <div class="card"
                             @click="selectedPost = post"
                             :style="selectedPost && selectedPost.id === post.id ? 'border: 2px solid #002FA7; box-shadow: 4px 4px 0 #002FA7;' : ''"
                             style="padding:1rem;cursor:pointer;transition:all 0.1s;">
                            
                            {{-- Post Meta Header --}}
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;font-family:var(--font-mono);font-size:0.6875rem;">
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <template x-if="post.platform?.toLowerCase() === 'x' || post.platform?.toLowerCase() === 'twitter'">
                                        <span class="badge badge-black" style="font-size:0.625rem;">𝕏 TWITTER</span>
                                    </template>
                                    <template x-if="post.platform?.toLowerCase() === 'threads'">
                                        <span class="badge badge-mono" style="font-size:0.625rem;">⊙ THREADS</span>
                                    </template>
                                    <span style="font-weight:700;color:#0A0A0A;" x-text="post.author_username ? '@' + post.author_username : '@anonim'"></span>
                                    <span style="color:var(--color-text-muted);" x-text="post.post_date ? post.post_date.split('T')[0] : ''"></span>
                                </div>

                                <div style="display:flex;align-items:center;gap:0.375rem;">
                                    <template x-if="post.classification?.label_lvl1 === 'hate_speech'">
                                        <span class="badge badge-hate" style="font-size:0.625rem;">HATE SPEECH</span>
                                    </template>
                                    <template x-if="post.classification?.label_lvl1 !== 'hate_speech'">
                                        <span class="badge badge-safe" style="font-size:0.625rem;">NON-HATE</span>
                                    </template>
                                    <span class="badge badge-mono" style="font-size:0.625rem;" x-text="formatLabel(post.classification?.label_lvl2)"></span>
                                </div>
                            </div>

                            {{-- Post Text Verbatim --}}
                            <p style="font-size:0.875rem;line-height:1.55;color:#0A0A0A;margin:0 0 0.5rem;font-weight:500;"
                               x-text="post.classification?.raw_content || '—'"></p>

                            {{-- Confidence & Footer --}}
                            <div style="display:flex;align-items:center;justify-content:space-between;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);border-top:1px dashed var(--color-border-subtle);padding-top:0.375rem;">
                                <span>CONFIDENCE: <strong style="color:#0A0A0A;" x-text="post.classification ? (post.classification.confidence_lvl1 * 100).toFixed(1) + '%' : '—'"></strong></span>
                                <span style="color:var(--color-primary);font-weight:700;">KLIK INSPEKSI [→]</span>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Pagination Controls --}}
                <div class="card" style="padding:0.75rem 1rem;display:flex;align-items:center;justify-content:space-between;font-family:var(--font-mono);font-size:0.75rem;flex-wrap:wrap;gap:0.5rem;">
                    <span>HALAMAN <strong x-text="pagination.current_page || 1"></strong> DARI <strong x-text="pagination.last_page || 1"></strong></span>

                    <div style="display:flex;gap:0.375rem;">
                        <button type="button" :disabled="!pagination.prev_page_url || loadingPosts"
                                @click="fetchPosts(pagination.current_page - 1)" class="btn btn-outline btn-sm"
                                :style="(!pagination.prev_page_url || loadingPosts) ? 'opacity:0.4;cursor:not-allowed;' : ''">
                            ← PREV
                        </button>
                        <button type="button" :disabled="!pagination.next_page_url || loadingPosts"
                                @click="fetchPosts(pagination.current_page + 1)" class="btn btn-outline btn-sm"
                                :style="(!pagination.next_page_url || loadingPosts) ? 'opacity:0.4;cursor:not-allowed;' : ''">
                            NEXT →
                        </button>
                    </div>
                </div>

            </div>

            {{-- ── RIGHT PANE: Deep-Dive Sticky Incident Inspector (30%) ── --}}
            <div style="position:sticky;top:72px;">
                <div class="card" style="padding:1.25rem;">
                    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:0.625rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <span class="badge badge-black" style="font-size:0.625rem;">INSPECTOR § 02.2</span>
                            <h3 style="font-size:1rem;font-weight:900;color:#0A0A0A;margin:2px 0 0;">Detail Tiket Insiden</h3>
                        </div>
                        <template x-if="selectedPost">
                            <span class="badge badge-mono" style="font-size:0.625rem;" x-text="'#POST-' + selectedPost.id"></span>
                        </template>
                    </div>

                    <template x-if="!selectedPost">
                        <div style="padding:2.5rem 1rem;text-align:center;font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">
                            [PILIH SALAH SATU POSTINGAN DI KIRI UNTUK INSPEKSI MENDALAM DENGAN XAI HIGHLIGHTER]
                        </div>
                    </template>

                    <template x-if="selectedPost">
                        <div style="display:flex;flex-direction:column;gap:1rem;">
                            {{-- Post Meta --}}
                            <div class="card-flat" style="padding:0.75rem;font-family:var(--font-mono);font-size:0.75rem;display:flex;flex-direction:column;gap:0.25rem;">
                                <div>AKUN: <strong style="color:#0A0A0A;" x-text="selectedPost.author_username ? '@' + selectedPost.author_username : '@anonim'"></strong></div>
                                <div>SUMBER: <span class="badge badge-mono" x-text="selectedPost.platform"></span></div>
                                <div x-show="selectedPost.source_url">
                                    <a :href="selectedPost.source_url" target="_blank" style="color:var(--color-primary);text-decoration:none;font-weight:700;">
                                        BUKA URL ASLI [↗]
                                    </a>
                                </div>
                            </div>

                            {{-- XAI Word Highlighter Section --}}
                            <div>
                                <span class="stat-block-label">EXPLAINABLE AI (XAI) ATTENTION TOKENS:</span>
                                <div style="background:#FFFFFF;border:1px solid #0A0A0A;padding:0.875rem;margin-top:0.375rem;line-height:1.8;font-size:0.875rem;color:#0A0A0A;">
                                    <div x-html="renderXaiHighlighted(selectedPost.classification?.raw_content, selectedPost.classification?.label_lvl1)"></div>
                                </div>
                                <span style="font-family:var(--font-mono);font-size:0.65rem;color:var(--color-text-subtle);margin-top:2px;display:block;">
                                    *Kata dengan latar kuning/border adalah token pemicu sentimen negatif/slang.
                                </span>
                            </div>

                            {{-- Hierarchical Gauges --}}
                            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                                <span class="stat-block-label">BREAKDOWN SKOR MODEL HIERARKIS:</span>
                                
                                {{-- L1 Sentimen --}}
                                <div style="border:1px solid #0A0A0A;padding:0.625rem;background:var(--color-surface-2);">
                                    <div style="display:flex;justify-content:space-between;font-family:var(--font-mono);font-size:0.725rem;font-weight:700;margin-bottom:0.25rem;">
                                        <span>LEVEL 1: SENTIMEN</span>
                                        <span x-text="selectedPost.classification ? (selectedPost.classification.confidence_lvl1 * 100).toFixed(1) + '%' : '—'"></span>
                                    </div>
                                    <div style="height:6px;background:#D4D3CB;border:1px solid #0A0A0A;overflow:hidden;">
                                        <div :style="'width:' + (selectedPost.classification?.confidence_lvl1 * 100) + '%;background:' + (selectedPost.classification?.label_lvl1 === 'hate_speech' ? 'var(--color-danger)' : 'var(--color-primary)')" style="height:100%;"></div>
                                    </div>
                                    <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin-top:0.25rem;display:block;"
                                          x-text="selectedPost.classification?.label_lvl1 === 'hate_speech' ? 'UJARAN KEBENCIAN' : 'KONTEN AMAN'"></span>
                                </div>

                                {{-- L2 Sub-Kategori --}}
                                <div style="border:1px solid #0A0A0A;padding:0.625rem;background:#FFFFFF;">
                                    <div style="display:flex;justify-content:space-between;font-family:var(--font-mono);font-size:0.725rem;font-weight:700;margin-bottom:0.25rem;">
                                        <span>LEVEL 2: TAKSONOMI</span>
                                        <span x-text="selectedPost.classification ? (selectedPost.classification.confidence_lvl2 * 100).toFixed(1) + '%' : '—'"></span>
                                    </div>
                                    <div style="height:6px;background:#D4D3CB;border:1px solid #0A0A0A;overflow:hidden;">
                                        <div :style="'width:' + (selectedPost.classification?.confidence_lvl2 * 100) + '%;background:#0A0A0A;'" style="height:100%;"></div>
                                    </div>
                                    <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:var(--color-primary);margin-top:0.25rem;display:block;"
                                          x-text="formatLabel(selectedPost.classification?.label_lvl2)"></span>
                                </div>
                            </div>

                            {{-- Preprocessing Normalization Info --}}
                            <div class="card-flat" style="padding:0.625rem;font-family:var(--font-mono);font-size:0.6875rem;">
                                <span style="font-weight:700;color:var(--color-text-muted);">KAMUSALAY CLEANED TEXT:</span>
                                <p style="margin:0.25rem 0 0;color:#0A0A0A;word-break:break-all;" x-text="selectedPost.classification?.clean_content || selectedPost.classification?.raw_content || '—'"></p>
                            </div>

                        </div>
                    </template>
                </div>
            </div>

        </div>

    </div>

</div>

@endsection
