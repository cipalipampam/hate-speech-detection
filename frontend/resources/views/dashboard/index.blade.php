@extends('layouts.app')

@section('title', '§ 01.0 Overview Dashboard')

@section('breadcrumb')
<span>§ 01.0 OVERVIEW</span>
@endsection

@section('content')

{{-- ── Monograph Header ── --}}
<div style="border-bottom:2px solid #0A0A0A;padding-bottom:1.25rem;margin-bottom:2rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
            <span class="badge badge-black">DOSIR § 01.0</span>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);letter-spacing:0.04em;">TELEMETRI RISET KOMPARATIF</span>
        </div>
        <h1 style="font-size:2.25rem;font-weight:900;letter-spacing:-0.035em;color:#0A0A0A;margin:0;line-height:1.1;">
            INTELLIGENCE OVERVIEW
        </h1>
        <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
            Ringkasan metrik deteksi ujaran kebencian multi-platform berbasis Hierarchical IndoBERT.
        </p>
    </div>

    @can('run-analysis')
    <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-lg">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>+ INVESTIGASI BARU</span>
    </a>
    @endcan
</div>

{{-- ── 5 Stark Metric Blocks ── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem;margin-bottom:2rem;">

    {{-- Total Analisis --}}
    <div class="stat-block">
        <span class="stat-block-label">§ 01.1 SESI RISET</span>
        <span class="stat-block-val" id="stat-total-analyses">{{ number_format($metrics['total_analyses']) }}</span>
        <div class="stat-block-meta">
            <span>[STATUS: SELESAI & ARSIP]</span>
        </div>
    </div>

    {{-- Total Opini --}}
    <div class="stat-block">
        <span class="stat-block-label">§ 01.2 TOTAL POSTINGAN</span>
        <span class="stat-block-val" id="stat-total-opinions">{{ number_format($metrics['total_opinions']) }}</span>
        <div class="stat-block-meta">
            <span>[KORPUS TERFILTER 𝕏 + ⊙]</span>
        </div>
    </div>

    {{-- Hate Speech Detected (Hazard Flag) --}}
    <div class="stat-block" style="border-top:4px solid var(--color-danger);background:var(--color-danger-bg);">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span class="stat-block-label" style="color:#0A0A0A;">§ 01.3 UJARAN KEBENCIAN</span>
            <span class="badge badge-hate" style="font-size:0.625rem;">FLAGGED</span>
        </div>
        <span class="stat-block-val" id="stat-total-hate" style="color:#0A0A0A;">{{ number_format($metrics['total_hate']) }}</span>
        <div class="stat-block-meta" style="border-color:#0A0A0A;color:#0A0A0A;">
            <span>[TINGKAT RISIKO TINGGI]</span>
        </div>
    </div>

    {{-- Non-Hate Content (Klein Blue Verified) --}}
    <div class="stat-block" style="border-top:4px solid var(--color-primary);background:var(--color-primary-bg);">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span class="stat-block-label" style="color:var(--color-primary);">§ 01.4 KONTEN AMAN</span>
            <span class="badge badge-safe" style="font-size:0.625rem;">VERIFIED</span>
        </div>
        <span class="stat-block-val" id="stat-total-non-hate" style="color:var(--color-primary);">{{ number_format($metrics['total_non_hate']) }}</span>
        <div class="stat-block-meta" style="border-color:var(--color-primary);color:var(--color-primary);">
            <span>[NETRAL & NON-TOKSIK]</span>
        </div>
    </div>

    {{-- Avg Toxicity % --}}
    <div class="stat-block">
        <span class="stat-block-label">§ 01.5 RATA-RATA TOKSISITAS</span>
        <span class="stat-block-val" id="stat-avg-hate-pct">{{ $metrics['avg_hate_pct'] }}<span style="font-size:1.25rem;">%</span></span>
        <div class="stat-block-meta">
            <span>[CORPUS TOXICITY MEAN]</span>
        </div>
    </div>

</div>

{{-- ── Charts Row ── --}}
<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:1.5rem;margin-bottom:2rem;" class="charts-row">

    {{-- Sentiment Ratio Donut --}}
    <div class="card" style="padding:1.5rem;">
        <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.75rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <span class="badge badge-mono" style="font-size:0.65rem;">LEVEL 1 INFERENCE</span>
                <h2 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:4px 0 0;letter-spacing:-0.02em;">Rasio Sentimen Global</h2>
            </div>
            <span id="sentiment-n-count" style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;">N={{ number_format($metrics['total_opinions']) }}</span>
        </div>

        <div id="chart-sentiment-donut"></div>

        {{-- Stark Swiss Legend --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--color-border-subtle);">
            <div style="background:var(--color-danger);padding:0.625rem;border:1px solid #0A0A0A;">
                <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;display:block;color:#0A0A0A;">■ HATE SPEECH</span>
                <span id="legend-hate-count" style="font-size:1.125rem;font-weight:900;color:#0A0A0A;">{{ number_format($metrics['total_hate']) }}</span>
            </div>
            <div style="background:var(--color-primary);padding:0.625rem;border:1px solid #0A0A0A;color:#FFFFFF;">
                <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;display:block;">■ NON-HATE</span>
                <span id="legend-non-hate-count" style="font-size:1.125rem;font-weight:900;">{{ number_format($metrics['total_non_hate']) }}</span>
            </div>
        </div>
    </div>

    {{-- Sub-Kategori Level 2 Bar Chart --}}
    <div class="card" style="padding:1.5rem;">
        <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.75rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <span class="badge badge-mono" style="font-size:0.65rem;">LEVEL 2 TAXONOMY</span>
                <h2 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:4px 0 0;letter-spacing:-0.02em;">Distribusi 6 Sub-Kategori</h2>
            </div>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">INDOBERT-V2</span>
        </div>

        <div id="chart-level2-bar"></div>
    </div>

</div>

{{-- ── Bottom Row: Platform Disparity & Recent Analysis Dossier ── --}}
<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;" class="bottom-row">

    {{-- Platform Comparison --}}
    <div class="card" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between;">
        <div>
            <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.75rem;margin-bottom:1.25rem;">
                <span class="badge badge-mono" style="font-size:0.65rem;">MULTI-SOURCE CORPUS</span>
                <h2 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:4px 0 0;letter-spacing:-0.02em;">Komparasi Platform</h2>
            </div>

            <div id="chart-platform"></div>
        </div>

        <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--color-border-subtle);">
            <div style="display:flex;align-items:center;justify-content:space-between;font-family:var(--font-mono);font-size:0.75rem;">
                <span style="display:flex;align-items:center;gap:0.375rem;">
                    <span style="width:8px;height:8px;background:#0A0A0A;display:inline-block;border:1px solid #0A0A0A;"></span>
                    <span>𝕏 TWITTER CORPUS</span>
                </span>
                <span id="platform-twitter-count" style="font-weight:700;">{{ $platformChart['series'][0] ?? 0 }} DATA</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;font-family:var(--font-mono);font-size:0.75rem;">
                <span style="display:flex;align-items:center;gap:0.375rem;">
                    <span style="width:8px;height:8px;background:var(--color-primary);display:inline-block;border:1px solid #0A0A0A;"></span>
                    <span>⊙ THREADS CORPUS</span>
                </span>
                <span id="platform-threads-count" style="font-weight:700;">{{ $platformChart['series'][1] ?? 0 }} DATA</span>
            </div>
        </div>
    </div>

    {{-- Recent Analysis Dossier Table --}}
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:1rem 1.25rem;background:#0A0A0A;color:#FFFFFF;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span style="font-family:var(--font-mono);font-weight:800;font-size:0.875rem;">§ 01.6 DOSIR INVESTIGASI TERAKHIR</span>
            </div>
            <a href="{{ route('analyses.index') }}" style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:var(--color-danger);text-decoration:none;">
                ARSIP LENGKAP [→]
            </a>
        </div>

        <div class="table-wrapper" style="box-shadow:none;border:none;">
            <table>
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>JUDUL PENELITIAN / KUERI</th>
                        <th style="width:100px;text-align:center;">PLATFORM</th>
                        <th style="width:90px;text-align:center;">TOTAL DATA</th>
                        <th style="width:110px;text-align:center;">STATUS</th>
                    </tr>
                </thead>
                <tbody id="recent-analyses-tbody">
                    @fragment('recent-table')
                    @forelse($recentAnalyses as $analysis)
                    <tr>
                        <td style="font-family:var(--font-mono);font-weight:700;color:var(--color-primary);">
                            #{{ str_pad($analysis->id, 4, '0', STR_PAD_LEFT) }}
                        </td>
                        <td>
                            <a href="{{ route('analyses.show', $analysis->id) }}"
                               style="font-weight:700;color:#0A0A0A;text-decoration:none;display:block;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                               onmouseover="this.style.color='var(--color-primary)'" onmouseout="this.style.color='#0A0A0A'">
                                {{ $analysis->title }}
                            </a>
                            <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-subtle);">
                                {{ $analysis->created_at->format('Y-m-d H:i') }} ({{ $analysis->created_at->diffForHumans() }})
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge badge-mono">
                                {{ strtoupper($analysis->platform) }}
                            </span>
                        </td>
                        <td style="text-align:center;font-family:var(--font-mono);font-weight:700;">
                            {{ $analysis->statistic ? number_format($analysis->statistic->total_data) : '—' }}
                        </td>
                        <td style="text-align:center;">
                            @if($analysis->status === 'completed')
                                <span class="badge badge-safe">SELESAI</span>
                            @elseif($analysis->status === 'running')
                                <span class="badge badge-hate" style="animation:telemetry-pulse 1.5s infinite;">PROSES</span>
                            @elseif($analysis->status === 'failed')
                                <span class="badge" style="background:#FEE2E2;color:#991B1B;border-color:#991B1B;">GAGAL</span>
                            @else
                                <span class="badge badge-mono">ANTREAN</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="padding:3rem 1rem;text-align:center;font-family:var(--font-mono);color:var(--color-text-muted);">
                            [BELUM ADA DOSIR ANALISIS TERCATAT]
                            @can('run-analysis')
                            <div style="margin-top:0.75rem;">
                                <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-sm">+ MULAI ANALISIS PERTAMA</a>
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @endforelse
                    @endfragment
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sentimentData = @json($sentimentChart);
    const level2Data    = @json($level2Chart);
    const platformData  = @json($platformChart);
    const hasData       = {{ $metrics['total_opinions'] > 0 ? 'true' : 'false' }};
    const platHasData   = platformData.series && platformData.series.some(v => v > 0);

    // ── Sentiment Donut (Hazard Yellow & Klein Blue) ───────────────────
    const chartSentiment = new ApexCharts(document.getElementById('chart-sentiment-donut'), {
        series: hasData ? sentimentData.series : [1, 1],
        chart: { type: 'donut', height: 210, sparkline: { enabled: true } },
        labels: sentimentData.labels,
        colors: hasData ? ['#FACC15', '#002FA7'] : ['#E3E2DC', '#E3E2DC'],
        stroke: { width: 2, colors: ['#0A0A0A'] },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: hasData,
                        value: { fontSize: '1.375rem', fontWeight: 900, color: '#0A0A0A', fontFamily: 'Plus Jakarta Sans, sans-serif' },
                        total: {
                            show: true,
                            label: hasData ? 'TOTAL' : 'KOSONG',
                            fontSize: '0.6875rem',
                            fontWeight: 700,
                            color: '#525252',
                            fontFamily: 'JetBrains Mono, monospace',
                            formatter: (w) => {
                                const sum = w.globals.seriesTotals ? w.globals.seriesTotals.reduce((a, b) => a + b, 0) : 0;
                                return sum > 0 ? sum.toLocaleString('id') : (hasData ? '0' : '—');
                            }
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        tooltip: { enabled: hasData, theme: 'light', style: { fontFamily: 'JetBrains Mono, monospace' } }
    });
    chartSentiment.render();

    // ── Level 2 Bar Chart (Stark Solid Black / Klein Blue Bars) ────────
    const chartLevel2 = new ApexCharts(document.getElementById('chart-level2-bar'), {
        series: level2Data.series,
        chart: { type: 'bar', height: 230, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 0, barHeight: '55%' } },
        xaxis: {
            categories: level2Data.categories,
            labels: { style: { fontSize: '0.7rem', fontFamily: 'JetBrains Mono, monospace', colors: '#525252' } },
            axisBorder: { color: '#0A0A0A' },
            axisTicks: { color: '#0A0A0A' }
        },
        yaxis: {
            labels: { style: { fontSize: '0.725rem', fontWeight: 600, fontFamily: 'Plus Jakarta Sans, sans-serif', colors: '#0A0A0A' } }
        },
        colors: ['#0A0A0A'],
        stroke: { width: 1, colors: ['#002FA7'] },
        dataLabels: { enabled: false },
        grid: { strokeDashArray: 0, borderColor: '#E3E2DC' },
        tooltip: { theme: 'light', style: { fontFamily: 'JetBrains Mono, monospace' } }
    });
    chartLevel2.render();

    // ── Platform Donut (Monochrome & Klein Blue) ───────────────────────
    const chartPlatform = new ApexCharts(document.getElementById('chart-platform'), {
        series: platHasData ? platformData.series : [1, 1],
        chart: { type: 'donut', height: 175, sparkline: { enabled: true } },
        labels: platformData.labels,
        colors: platHasData ? ['#0A0A0A', '#002FA7'] : ['#E3E2DC', '#E3E2DC'],
        stroke: { width: 2, colors: ['#0A0A0A'] },
        plotOptions: {
            pie: { donut: { size: '65%', labels: { show: false } } }
        },
        dataLabels: { enabled: false },
        tooltip: { enabled: platHasData, theme: 'light', style: { fontFamily: 'JetBrains Mono, monospace' } }
    });
    chartPlatform.render();

    // ── Helper Pembaruan Teks dengan Micro-Animation ───────────────────
    function updateTextIfChanged(el, newText) {
        if (!el) return;
        const current = el.textContent.trim();
        const incoming = String(newText).trim();
        if (current !== incoming) {
            el.textContent = incoming;
            el.classList.remove('stat-updated');
            void el.offsetWidth; // Trigger reflow
            el.classList.add('stat-updated');
        }
    }

    function updateHtmlIfChanged(el, newHtml) {
        if (!el) return;
        if (el.innerHTML.trim() !== newHtml.trim()) {
            el.innerHTML = newHtml;
            el.classList.remove('stat-updated');
            void el.offsetWidth; // Trigger reflow
            el.classList.add('stat-updated');
        }
    }

    // ── Fungsi Pembaruan Seluruh Komponen Overview Secara Dinamis ───────
    function updateDashboardUI(data) {
        if (!data) return;

        // 1. Update 5 Card Metrik Utama
        if (data.metrics) {
            const m = data.metrics;
            updateTextIfChanged(document.getElementById('stat-total-analyses'), Number(m.total_analyses || 0).toLocaleString('id'));
            updateTextIfChanged(document.getElementById('stat-total-opinions'), Number(m.total_opinions || 0).toLocaleString('id'));
            updateTextIfChanged(document.getElementById('stat-total-hate'), Number(m.total_hate || 0).toLocaleString('id'));
            updateTextIfChanged(document.getElementById('stat-total-non-hate'), Number(m.total_non_hate || 0).toLocaleString('id'));
            updateHtmlIfChanged(document.getElementById('stat-avg-hate-pct'), `${m.avg_hate_pct ?? 0}<span style="font-size:1.25rem;">%</span>`);

            // 2. Update Card Rasio Sentimen (Legend & N Count)
            updateTextIfChanged(document.getElementById('sentiment-n-count'), `N=${Number(m.total_opinions || 0).toLocaleString('id')}`);
            updateTextIfChanged(document.getElementById('legend-hate-count'), Number(m.total_hate || 0).toLocaleString('id'));
            updateTextIfChanged(document.getElementById('legend-non-hate-count'), Number(m.total_non_hate || 0).toLocaleString('id'));
        }

        // 3. Update Grafik Rasio Sentimen Donut
        if (chartSentiment && data.sentimentChart && data.metrics) {
            const totalOpinions = Number(data.metrics.total_opinions) || 0;
            const hasDataNow = totalOpinions > 0;
            const newSeries = hasDataNow ? data.sentimentChart.series : [1, 1];
            const newColors = hasDataNow ? ['#FACC15', '#002FA7'] : ['#E3E2DC', '#E3E2DC'];

            chartSentiment.updateOptions({
                colors: newColors,
                plotOptions: {
                    pie: {
                        donut: {
                            labels: {
                                show: hasDataNow,
                                total: {
                                    show: true,
                                    label: hasDataNow ? 'TOTAL' : 'KOSONG',
                                    formatter: (w) => {
                                        const sum = w.globals.seriesTotals ? w.globals.seriesTotals.reduce((a, b) => a + b, 0) : 0;
                                        return sum > 0 ? sum.toLocaleString('id') : (hasDataNow ? '0' : '—');
                                    }
                                }
                            }
                        }
                    }
                },
                tooltip: { enabled: hasDataNow }
            }, false, false);
            chartSentiment.updateSeries(newSeries);
        }

        // 4. Update Grafik Distribusi Sub-Kategori Level 2
        if (chartLevel2 && data.level2Chart) {
            chartLevel2.updateOptions({
                xaxis: {
                    categories: data.level2Chart.categories
                }
            }, false, false);
            chartLevel2.updateSeries(data.level2Chart.series);
        }

        // 5. Update Komparasi Platform (Donut & Counts)
        if (data.platformChart) {
            const pSeries = data.platformChart.series || [0, 0];
            const pHasData = pSeries.some(v => v > 0);
            if (chartPlatform) {
                chartPlatform.updateOptions({
                    colors: pHasData ? ['#0A0A0A', '#002FA7'] : ['#E3E2DC', '#E3E2DC'],
                    tooltip: { enabled: pHasData }
                }, false, false);
                chartPlatform.updateSeries(pHasData ? pSeries : [1, 1]);
            }
            updateTextIfChanged(document.getElementById('platform-twitter-count'), `${Number(pSeries[0] || 0).toLocaleString('id')} DATA`);
            updateTextIfChanged(document.getElementById('platform-threads-count'), `${Number(pSeries[1] || 0).toLocaleString('id')} DATA`);
        }

        // 6. Update Tabel § 01.6 Dosir Investigasi Terakhir
        if (data.table_html) {
            const tbody = document.getElementById('recent-analyses-tbody');
            if (tbody && tbody.innerHTML !== data.table_html) {
                tbody.innerHTML = data.table_html;
            }
        }
    }

    // ── Polling Reaktif Adaptif (Fast saat running, Heartbeat saat idle) ──
    let pollTimer = null;
    let isCurrentlyRunning = {{ !empty($stillHasRunning) ? 'true' : 'false' }};

    async function fetchDashboardUpdates() {
        try {
            const res = await fetch('{{ route('dashboard') }}', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data && data.success) {
                updateDashboardUI(data);
                isCurrentlyRunning = Boolean(data.has_running);
            }
        } catch (err) {
            console.error('[Telemetry] Gagal polling data dashboard:', err);
        } finally {
            // Jika ada analisis yang sedang running: cek setiap 3 detik
            // Jika sudah selesai/idle: tetap cek detak jantung (heartbeat) setiap 15 detik
            scheduleNextPoll(isCurrentlyRunning ? 3000 : 15000);
        }
    }

    function scheduleNextPoll(ms) {
        if (pollTimer) clearTimeout(pollTimer);
        pollTimer = setTimeout(fetchDashboardUpdates, ms);
    }

    // Jalankan polling adaptif
    scheduleNextPoll(isCurrentlyRunning ? 3000 : 15000);

    window.addEventListener('beforeunload', () => {
        if (pollTimer) clearTimeout(pollTimer);
    });
});
</script>
<style>
@keyframes telemetry-flash {
    0% { color: var(--color-primary); transform: scale(1.04); }
    50% { color: var(--color-primary); }
    100% { transform: scale(1); }
}
.stat-updated {
    display: inline-block;
    animation: telemetry-flash 0.8s ease-out;
}
@media (max-width: 1024px) {
    .charts-row, .bottom-row { grid-template-columns: 1fr !important; }
}
</style>
@endpush
