/**
 * Alpine component for § 02.2 Analysis Dossier (Detail View):
 * Real-time pipeline progress, ApexCharts, filterable stream, XAI inspector.
 */
import ApexCharts from 'apexcharts';
import { formatLabel, numberFormat } from '../utils/labels';

// Instance grafik disimpan di luar proxy Alpine untuk mencegah degradasi performa
let sentimentChartInstance = null;
let level2ChartInstance = null;

export default function analysisDetail() {
    let initial = {
        analysisId: null,
        status: 'queued',
        stats: null,
        keywords: [],
        exportUrl: '',
        execTime: null,
        pipelineStep: 1,
        pipelineMessage: 'Checking the worker pipeline...',
        posts: [],
        selectedPost: null,
        pagination: {},
        filters: { search: '', label_lvl1: 'all', label_lvl2: 'all', platform: 'all' }
    };

    try {
        const el = document.getElementById('analysis-server-data');
        if (el && el.textContent) {
            initial = Object.assign(initial, JSON.parse(el.textContent));
        }
    } catch (e) {
        console.error('Failed to parse analysis server data:', e);
    }

    const detailEndpoint = initial.endpoint || window.location.pathname;

    return {
        analysisId:      initial.analysisId,
        status:          initial.status,
        stats:           initial.stats,
        keywords:        initial.keywords,
        exportUrl:       initial.exportUrl,
        execTime:        initial.execTime,
        pipelineStep:    initial.pipelineStep || 1,
        pipelineMessage: initial.pipelineMessage || 'Checking the worker pipeline...',
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
                    const res = await fetch(detailEndpoint, {
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
                            // Proteksi monotonik: progress bar tidak boleh mundur ke tahap lebih rendah
                            this.pipelineStep = Math.max(this.pipelineStep, data.analysis.pipeline_step);
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
                            this.pipelineMessage = 'The AI pipeline finished. All posts were downloaded and classified.';
                        } else if (this.status === 'failed') {
                            if (this.pollTimer) {
                                clearInterval(this.pollTimer);
                                this.pollTimer = null;
                            }
                            this.pipelineMessage = data.analysis.error_message || this.pipelineMessage;
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

                const res = await fetch(`${detailEndpoint}?${query.toString()}`, {
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
            return numberFormat(num);
        },

        formatLabel(label) {
            return formatLabel(label);
        },

        renderCharts() {
            if (typeof ApexCharts === 'undefined') return;
            if (this.chartsRendered) return;
            this.chartsRendered = true;

            const hateCount = this.stats?.hate_speech_count || 0;
            const nonHateCount = this.stats?.non_hate_speech_count || 0;

            const elSentiment = document.querySelector('#session-sentiment-chart');
            if (elSentiment && (hateCount > 0 || nonHateCount > 0)) {
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
                'Institutional Delegitimization',
                'Dehumanization',
                'Incitement to Violence',
                'Hate-Baiting Hoax',
                'Religious & Personal Abuse',
                'Non-Hate / Neutral'
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
                    series: [{ name: 'Posts', data: dataCounts }],
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
