/**
 * Dashboard Overview: ApexCharts visualization + adaptive polling with anti-flicker state tracking.
 */
import ApexCharts from 'apexcharts';

export default function initDashboard() {
    const dataIsland = document.getElementById('dashboard-server-data');
    if (!dataIsland) return;

    let serverData = {};
    try {
        serverData = JSON.parse(dataIsland.textContent || '{}');
    } catch (e) {
        console.error('Failed to parse dashboard server data:', e);
        return;
    }

    const sentimentData = serverData.sentimentChart || { series: [0, 0], labels: ['Hate Speech', 'Non-Hate'] };
    const level2Data    = serverData.level2Chart || { series: [], categories: [] };
    const platformData  = serverData.platformChart || { series: [0, 0], labels: ['X (Twitter)', 'Threads'] };
    const totalOpinions = Number(serverData.metrics?.total_opinions || 0);
    const hasData       = totalOpinions > 0;
    const platHasData   = Array.isArray(platformData.series) && platformData.series.some(v => v > 0);
    const dashboardEndpoint = serverData.endpoint || '/dashboard';

    const elSentiment = document.getElementById('chart-sentiment-donut');
    const elLevel2    = document.getElementById('chart-level2-bar');
    const elPlatform  = document.getElementById('chart-platform');

    if (!elSentiment || !elLevel2 || !elPlatform) return;

    // ── 1. Sentiment Donut (Hazard Yellow & Klein Blue) ───────────────────
    const chartSentiment = new ApexCharts(elSentiment, {
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
                            label: hasData ? 'TOTAL' : 'EMPTY',
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

    // ── 2. Level 2 Bar Chart (Stark Solid Black / Klein Blue Bars) ────────
    const chartLevel2 = new ApexCharts(elLevel2, {
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

    // ── 3. Platform Donut (Monochrome & Klein Blue) ───────────────────────
    const chartPlatform = new ApexCharts(elPlatform, {
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

    // ── Text & HTML Update Helpers with Micro-Animation ──────────────────
    function updateTextIfChanged(el, newText) {
        if (!el) return;
        const current = el.textContent.trim();
        const incoming = String(newText).trim();
        if (current !== incoming) {
            el.textContent = incoming;
            el.classList.remove('stat-updated');
            void el.offsetWidth;
            el.classList.add('stat-updated');
        }
    }

    function updateHtmlIfChanged(el, newHtml) {
        if (!el) return;
        if (el.innerHTML.trim() !== newHtml.trim()) {
            el.innerHTML = newHtml;
            el.classList.remove('stat-updated');
            void el.offsetWidth;
            el.classList.add('stat-updated');
        }
    }

    // ── Anti-Flicker State Tracker: mencegah ApexCharts re-render tak perlu
    let _lastSentimentSeries = JSON.stringify(hasData ? sentimentData.series : [1, 1]);
    let _lastSentimentColors = JSON.stringify(hasData ? ['#FACC15', '#002FA7'] : ['#E3E2DC', '#E3E2DC']);
    let _lastLevel2Series    = JSON.stringify(level2Data.series);
    let _lastLevel2Cats      = JSON.stringify(level2Data.categories);
    let _lastPlatformSeries  = JSON.stringify(platHasData ? platformData.series : [1, 1]);
    let _lastPlatformColors  = JSON.stringify(platHasData ? ['#0A0A0A', '#002FA7'] : ['#E3E2DC', '#E3E2DC']);

    function updateDashboardUI(data) {
        if (!data) return;

        // Update 4 Metrik Utama
        if (data.metrics) {
            const m = data.metrics;
            updateTextIfChanged(document.getElementById('stat-total-analyses'), Number(m.total_analyses || 0).toLocaleString('id'));
            updateTextIfChanged(document.getElementById('stat-total-hate'), Number(m.total_hate || 0).toLocaleString('id'));
            updateTextIfChanged(document.getElementById('stat-total-non-hate'), Number(m.total_non_hate || 0).toLocaleString('id'));
            updateHtmlIfChanged(document.getElementById('stat-avg-hate-pct'), `${m.avg_hate_pct ?? 0}<span style="font-size:1.25rem;">%</span>`);

            updateTextIfChanged(document.getElementById('sentiment-n-count'), `N=${Number(m.total_opinions || 0).toLocaleString('id')} RECORDS`);
            updateTextIfChanged(document.getElementById('legend-hate-count'), Number(m.total_hate || 0).toLocaleString('id'));
            updateTextIfChanged(document.getElementById('legend-non-hate-count'), Number(m.total_non_hate || 0).toLocaleString('id'));
        }

        // Update Sentiment Donut Chart
        if (chartSentiment && data.sentimentChart && data.metrics) {
            const opinions = Number(data.metrics.total_opinions) || 0;
            const hasDataNow = opinions > 0;
            const newSeries = hasDataNow ? data.sentimentChart.series : [1, 1];
            const newColors = hasDataNow ? ['#FACC15', '#002FA7'] : ['#E3E2DC', '#E3E2DC'];
            const seriesKey = JSON.stringify(newSeries);
            const colorsKey = JSON.stringify(newColors);

            if (seriesKey !== _lastSentimentSeries || colorsKey !== _lastSentimentColors) {
                _lastSentimentSeries = seriesKey;
                _lastSentimentColors = colorsKey;
                chartSentiment.updateOptions({
                    colors: newColors,
                    plotOptions: {
                        pie: {
                            donut: {
                                labels: {
                                    show: hasDataNow,
                                    total: {
                                        show: true,
                                        label: hasDataNow ? 'TOTAL' : 'EMPTY',
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
        }

        // Update Level 2 Bar Chart
        if (chartLevel2 && data.level2Chart) {
            const newSeries2 = data.level2Chart.series;
            const newCats2   = data.level2Chart.categories;
            const seriesKey2 = JSON.stringify(newSeries2);
            const catsKey2   = JSON.stringify(newCats2);

            if (seriesKey2 !== _lastLevel2Series || catsKey2 !== _lastLevel2Cats) {
                _lastLevel2Series = seriesKey2;
                _lastLevel2Cats   = catsKey2;
                chartLevel2.updateOptions({ xaxis: { categories: newCats2 } }, false, false);
                chartLevel2.updateSeries(newSeries2);
            }
        }

        // Update Platform Donut Chart
        if (data.platformChart) {
            const pSeries    = data.platformChart.series || [0, 0];
            const pHasData   = pSeries.some(v => v > 0);
            const pSeriesEff = pHasData ? pSeries : [1, 1];
            const pColors    = pHasData ? ['#0A0A0A', '#002FA7'] : ['#E3E2DC', '#E3E2DC'];
            const pSeriesKey = JSON.stringify(pSeriesEff);
            const pColorKey  = JSON.stringify(pColors);

            if (chartPlatform && (pSeriesKey !== _lastPlatformSeries || pColorKey !== _lastPlatformColors)) {
                _lastPlatformSeries = pSeriesKey;
                _lastPlatformColors = pColorKey;
                chartPlatform.updateOptions({
                    colors: pColors,
                    tooltip: { enabled: pHasData }
                }, false, false);
                chartPlatform.updateSeries(pSeriesEff);
            }
            updateTextIfChanged(document.getElementById('platform-twitter-count'), `${Number(pSeries[0] || 0).toLocaleString('id')} DATA`);
            updateTextIfChanged(document.getElementById('platform-threads-count'), `${Number(pSeries[1] || 0).toLocaleString('id')} DATA`);
        }

        // Update Recent Analysis Dossier Table
        if (data.table_html) {
            const tbody = document.getElementById('recent-analyses-tbody');
            if (tbody && tbody.innerHTML !== data.table_html) {
                tbody.innerHTML = data.table_html;
            }
        }
    }

    // ── Adaptive Polling ─────────────────────────────────────────────────
    let pollTimer = null;
    let isCurrentlyRunning = Boolean(serverData.hasRunning);

    async function fetchDashboardUpdates() {
        try {
            const res = await fetch(dashboardEndpoint, {
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
            console.error('[Telemetry] Dashboard polling failed:', err);
        } finally {
            scheduleNextPoll(isCurrentlyRunning ? 3000 : 15000);
        }
    }

    function scheduleNextPoll(ms) {
        if (pollTimer) clearTimeout(pollTimer);
        pollTimer = setTimeout(fetchDashboardUpdates, ms);
    }

    scheduleNextPoll(isCurrentlyRunning ? 3000 : 15000);

    window.addEventListener('beforeunload', () => {
        if (pollTimer) clearTimeout(pollTimer);
    });
}
