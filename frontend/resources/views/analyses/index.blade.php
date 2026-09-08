@extends('layouts.app')

@section('title', '§ 02.0 Riwayat Analisis')

@section('breadcrumb')
<span style="color:#0A0A0A;">§ 02.0 RIWAYAT</span>
@endsection

@section('content')
<script>
window.__INITIAL_ANALYSES__ = {!! json_encode([
    'items'      => $analyses->items(),
    'pagination' => [
        'current_page'  => $analyses->currentPage(),
        'last_page'     => $analyses->lastPage(),
        'total'         => $analyses->total(),
        'from'          => $analyses->firstItem() ?? 0,
        'to'            => $analyses->lastItem() ?? 0,
        'prev_page_url' => $analyses->previousPageUrl(),
        'next_page_url' => $analyses->nextPageUrl(),
    ],
    'filters'    => [
        'search'   => request('search', ''),
        'platform' => request('platform', 'all'),
        'status'   => request('status', 'all'),
    ]
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};

function analysesIndex() {
    const initial = window.__INITIAL_ANALYSES__ || {
        items: [],
        pagination: {},
        filters: { search: '', platform: 'all', status: 'all' }
    };

    return {
        analyses:   initial.items || [],
        pagination: initial.pagination || {},
        filters:    initial.filters || { search: '', platform: 'all', status: 'all' },
        loading:    false,
        pollTimer:  null,

        init() {
            if (!this.analyses || this.analyses.length === 0) {
                if (window.__INITIAL_ANALYSES__ && window.__INITIAL_ANALYSES__.items && window.__INITIAL_ANALYSES__.items.length > 0) {
                    this.analyses = window.__INITIAL_ANALYSES__.items;
                    this.pagination = window.__INITIAL_ANALYSES__.pagination;
                    this.filters = window.__INITIAL_ANALYSES__.filters;
                }
            }
            this.checkAndStartPolling();
        },

        checkAndStartPolling() {
            const anyRunning = this.analyses && this.analyses.some(item => item.status === 'running' || item.status === 'queued');
            if (anyRunning && !this.pollTimer) {
                this.pollTimer = setInterval(() => {
                    this.fetchData(this.pagination.current_page || 1, false);
                }, 3000);
            } else if (!anyRunning && this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        async fetchData(page = 1, showLoading = true) {
            if (showLoading) {
                this.loading = true;
            }
            try {
                const query = new URLSearchParams({
                    page:     page,
                    search:   this.filters.search || '',
                    platform: this.filters.platform || 'all',
                    status:   this.filters.status || 'all',
                });

                const res = await fetch(`{{ route('analyses.index') }}?${query.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });
                const data = await res.json();
                if (data && data.analyses) {
                    this.analyses = data.analyses.data || [];
                    this.pagination = {
                        current_page:  data.analyses.current_page,
                        last_page:     data.analyses.last_page,
                        total:         data.analyses.total,
                        from:          data.analyses.from,
                        to:            data.analyses.to,
                        prev_page_url: data.analyses.prev_page_url,
                        next_page_url: data.analyses.next_page_url,
                    };
                    this.checkAndStartPolling();
                }
            } catch (e) {
                console.error('Failed to fetch analyses:', e);
            } finally {
                if (showLoading) {
                    this.loading = false;
                }
            }
        },

        resetFilters() {
            this.filters.search = '';
            this.filters.platform = 'all';
            this.filters.status = 'all';
            this.fetchData(1);
        },

        numberFormat(num) {
            return new Intl.NumberFormat('id-ID').format(num || 0);
        },

        getKeywordList(item) {
            if (!item || !item.keywords) return [];
            if (Array.isArray(item.keywords)) return item.keywords;
            if (typeof item.keywords === 'string') {
                try {
                    const p = JSON.parse(item.keywords);
                    if (Array.isArray(p)) return p;
                } catch(e) {}
                return [item.keywords];
            }
            return [];
        },

        getUserName(item) {
            return (item && item.user && item.user.name) ? item.user.name : 'SYSTEM';
        },

        formatDate(item) {
            return (item && item.created_at) ? item.created_at.substring(0, 10) : '';
        }
    };
}
</script>

<div x-data="analysesIndex()">

    {{-- Monograph Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1.25rem;margin-bottom:2rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                <span class="badge badge-black">SEKSI § 02.0</span>
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">ARSIP DOSIR HISTORIS</span>
            </div>
            <h1 style="font-size:2.25rem;font-weight:900;letter-spacing:-0.035em;color:#0A0A0A;margin:0;line-height:1.1;">
                RIWAYAT INVESTIGASI KORPUS
            </h1>
            <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
                Katalog berkas investigasi ujaran kebencian multi-platform yang tersimpan dalam repositori riset.
            </p>
        </div>

        @can('run-analysis')
        <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-lg">
            <span>+ INVESTIGASI BARU</span>
        </a>
        @endcan
    </div>

    {{-- Filter Toolbar --}}
    <div class="card" style="padding:0.75rem 1rem;margin-bottom:1.5rem;">
        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
            
            {{-- Debounced Search Input --}}
            <div style="flex:1;min-width:240px;position:relative;">
                <input type="text"
                       x-model.debounce.350ms="filters.search"
                       @input="fetchData(1)"
                       placeholder="Cari judul riset atau kata kunci topik... (/)"
                       class="input"
                       style="height:38px;padding-left:0.75rem;font-size:0.8125rem;">
            </div>
            
            {{-- Platform Filter --}}
            <select x-model="filters.platform" @change="fetchData(1)" class="input" style="width:auto;height:38px;font-size:0.8125rem;cursor:pointer;">
                <option value="all">SEMUA PLATFORM</option>
                <option value="x">𝕏 TWITTER</option>
                <option value="threads">⊙ THREADS</option>
                <option value="both">DUAL PLATFORM</option>
            </select>

            {{-- Status Filter --}}
            <select x-model="filters.status" @change="fetchData(1)" class="input" style="width:auto;height:38px;font-size:0.8125rem;cursor:pointer;">
                <option value="all">SEMUA STATUS</option>
                <option value="completed">SELESAI</option>
                <option value="running">PROSES</option>
                <option value="failed">GAGAL</option>
                <option value="queued">ANTREAN</option>
            </select>

        </div>
    </div>

    {{-- Monograph Table Wrapper --}}
    <div class="table-wrapper" style="position:relative;">
        
        {{-- Loading Skeleton Overlay --}}
        <div x-show="loading" x-cloak style="position:absolute;inset:0;background:rgba(246,245,240,0.8);display:flex;align-items:center;justify-content:center;z-index:10;">
            <span class="badge badge-black">MEMPERBARUI ARSIP...</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>JUDUL PENELITIAN & PENELITI</th>
                    <th style="width:120px;text-align:center;">PLATFORM</th>
                    <th>KATA KUNCI TOPIK</th>
                    <th style="width:90px;text-align:center;">TOTAL DATA</th>
                    <th style="width:90px;text-align:center;">HATE %</th>
                    <th style="width:110px;text-align:center;">STATUS</th>
                    <th style="width:130px;text-align:right;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <template x-if="analyses.length === 0 && !loading">
                    <tr>
                        <td colspan="8" style="padding:4rem 1rem;text-align:center;font-family:var(--font-mono);color:var(--color-text-muted);">
                            [TIDAK DITEMUKAN BERKAS INVESTIGASI SESUAI FILTER]
                        </td>
                    </tr>
                </template>

                <template x-for="(item, index) in analyses" :key="item.id">
                    <tr>
                        <td style="font-family:var(--font-mono);font-weight:700;color:var(--color-primary);">
                            #<span x-text="String(item.id).padStart(4, '0')"></span>
                        </td>
                        <td>
                            <a :href="'/analyses/' + item.id"
                               style="font-weight:800;color:#0A0A0A;text-decoration:none;display:block;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.875rem;"
                               onmouseover="this.style.color='var(--color-primary)'"
                               onmouseout="this.style.color='#0A0A0A'"
                               x-text="item.title">
                            </a>
                            <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);"
                                  x-text="getUserName(item) + ' · ' + formatDate(item)"></span>
                        </td>
                        <td style="text-align:center;">
                            <template x-if="item.platform?.toLowerCase() === 'x'">
                                <span class="badge badge-black" style="font-size:0.65rem;">𝕏 TWITTER</span>
                            </template>
                            <template x-if="item.platform?.toLowerCase() === 'threads'">
                                <span class="badge badge-mono" style="font-size:0.65rem;">⊙ THREADS</span>
                            </template>
                            <template x-if="item.platform?.toLowerCase() === 'both'">
                                <span class="badge badge-safe" style="font-size:0.65rem;">DUAL 𝕏+⊙</span>
                            </template>
                        </td>
                        <td>
                            <div style="display:flex;gap:3px;flex-wrap:wrap;max-width:200px;">
                                <template x-for="kw in getKeywordList(item).slice(0, 3)" :key="kw">
                                    <span class="badge badge-mono" style="font-size:0.625rem;" x-text="'#' + kw"></span>
                                </template>
                                <template x-if="getKeywordList(item).length > 3">
                                    <span style="font-family:var(--font-mono);font-size:0.625rem;color:var(--color-text-muted);align-self:center;" x-text="'+' + (getKeywordList(item).length - 3)"></span>
                                </template>
                            </div>
                        </td>
                        <td style="text-align:center;font-family:var(--font-mono);font-weight:700;" x-text="item.statistic ? numberFormat(item.statistic.total_data) : '—'"></td>
                        <td style="text-align:center;font-family:var(--font-mono);font-weight:700;">
                            <template x-if="item.statistic">
                                <span :style="item.statistic.hate_speech_pct > 20 ? 'color:#0A0A0A;background:var(--color-danger);padding:1px 4px;border:1px solid #0A0A0A;' : ''"
                                      x-text="(item.statistic.hate_speech_pct || 0) + '%'"></span>
                            </template>
                            <template x-if="!item.statistic"><span>—</span></template>
                        </td>
                        <td style="text-align:center;">
                            <template x-if="item.status === 'completed'">
                                <span class="badge badge-safe">SELESAI</span>
                            </template>
                            <template x-if="item.status === 'running'">
                                <span class="badge badge-hate" style="animation:telemetry-pulse 1.2s infinite;">PROSES</span>
                            </template>
                            <template x-if="item.status === 'failed'">
                                <span class="badge" style="background:#FEE2E2;color:#991B1B;border-color:#991B1B;">GAGAL</span>
                            </template>
                            <template x-if="item.status === 'queued'">
                                <span class="badge badge-mono">ANTREAN</span>
                            </template>
                        </td>
                        <td style="text-align:right;">
                            <a :href="'/analyses/' + item.id" class="btn btn-outline btn-sm" style="font-size:0.6875rem;padding:0.25rem 0.5rem;">
                                DOSIR [→]
                            </a>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        {{-- Dynamic Pagination Footer --}}
        <div style="padding:0.75rem 1rem;background:#FFFFFF;border-top:1px solid #0A0A0A;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;font-family:var(--font-mono);font-size:0.75rem;">
            <span>
                MENAMPILKAN <strong x-text="pagination.from || 0"></strong>–<strong x-text="pagination.to || 0"></strong> DARI <strong x-text="pagination.total || 0"></strong> TOTAL BERKAS
            </span>

            <div style="display:flex;align-items:center;gap:0.375rem;">
                <button type="button"
                        :disabled="!pagination.prev_page_url || loading"
                        @click="fetchData(pagination.current_page - 1)"
                        class="btn btn-outline btn-sm"
                        :style="(!pagination.prev_page_url || loading) ? 'opacity:0.4;cursor:not-allowed;' : ''">
                    ← PREV
                </button>
                
                <span style="padding:0 0.5rem;" x-text="'HAL ' + (pagination.current_page || 1) + ' / ' + (pagination.last_page || 1)"></span>

                <button type="button"
                        :disabled="!pagination.next_page_url || loading"
                        @click="fetchData(pagination.current_page + 1)"
                        class="btn btn-outline btn-sm"
                        :style="(!pagination.next_page_url || loading) ? 'opacity:0.4;cursor:not-allowed;' : ''">
                    NEXT →
                </button>
            </div>
        </div>
    </div>

</div>

@endsection
