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
        loading:    false,
        pollTimer:  null,

        init() {
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

                const res = await fetch(`${indexEndpoint}?${query.toString()}`, {
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
