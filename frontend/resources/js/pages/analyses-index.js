/**
 * Alpine component for § 02.0 Analysis History (Index View):
 * Real-time polling for running jobs, filterable table, pagination.
 */
import { numberFormat } from '../utils/labels';

export default function analysesIndex() {
    let initial = {
        items: [],
        pagination: {},
        filters: { search: '', platform: 'all', status: 'all' },
        endpoint: window.location.pathname
    };

    try {
        const el = document.getElementById('analyses-index-data');
        if (el && el.textContent) {
            initial = Object.assign(initial, JSON.parse(el.textContent));
        }
    } catch (e) {
        console.error('Failed to parse analyses-index data:', e);
    }

    const indexEndpoint = initial.endpoint || window.location.pathname;

    return {
        analyses:   initial.items || [],
        pagination: initial.pagination || {},
        filters:    initial.filters || { search: '', platform: 'all', status: 'all' },
        hasRunning: Boolean(initial.has_running),
        loading:    false,
        pollTimer:  null,
        requestController: null,
        requestSequence: 0,

        init() {
            this.checkAndStartPolling();
        },

        checkAndStartPolling() {
            const anyRunning = this.hasRunning || (this.analyses && this.analyses.some(item => item.status === 'running' || item.status === 'queued'));
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
            const requestId = ++this.requestSequence;

            if (this.requestController) {
                this.requestController.abort();
            }

            const controller = new AbortController();
            this.requestController = controller;

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

                const res = await fetch(`${indexEndpoint}?${query.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                });

                if (!res.ok) {
                    throw new Error(`Analysis request failed with status ${res.status}`);
                }

                const data = await res.json();
                if (requestId === this.requestSequence && data && data.analyses) {
                    this.hasRunning = Boolean(data.has_running);
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
                if (e.name !== 'AbortError') {
                    console.error('Failed to fetch analyses:', e);
                }
            } finally {
                if (requestId === this.requestSequence && showLoading) {
                    this.loading = false;
                }
                if (this.requestController === controller) {
                    this.requestController = null;
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
            return numberFormat(num);
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
