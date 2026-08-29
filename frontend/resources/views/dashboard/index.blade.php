@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb')
<div style="display:flex;align-items:center;gap:0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
    <span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">Dashboard</span>
</div>
@endsection

@section('content')

{{-- ── Page Header ── --}}
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Ringkasan statistik analisis ujaran kebencian secara keseluruhan.</p>
    </div>
    @can('run-analysis')
    <a href="{{ route('analyses.create') }}" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Analisis Baru
    </a>
    @endcan
</div>

{{-- ── Metric Cards ── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.75rem;">

    {{-- Total Analisis --}}
    <div class="metric-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;">
            <div class="metric-card-icon" style="background:var(--color-primary-bg);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <span class="badge badge-primary" style="font-size:0.6875rem;">Total</span>
        </div>
        <div>
            <p class="metric-card-value">{{ number_format($metrics['total_analyses']) }}</p>
            <p class="metric-card-label">Sesi Analisis</p>
        </div>
        <div class="metric-card-accent" style="background:var(--color-primary);"></div>
    </div>

    {{-- Total Opini --}}
    <div class="metric-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;">
            <div class="metric-card-icon" style="background:#EEF3F8;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-navy)" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <span class="badge badge-navy" style="font-size:0.6875rem;">Postingan</span>
        </div>
        <div>
            <p class="metric-card-value">{{ number_format($metrics['total_opinions']) }}</p>
            <p class="metric-card-label">Total Opini Teranalisis</p>
        </div>
        <div class="metric-card-accent" style="background:var(--color-navy);"></div>
    </div>

    {{-- Hate Speech --}}
    <div class="metric-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;">
            <div class="metric-card-icon" style="background:var(--color-danger-bg);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-danger)" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <span class="badge badge-hate" style="font-size:0.6875rem;">Hate</span>
        </div>
        <div>
            <p class="metric-card-value" style="color:var(--color-danger);">{{ number_format($metrics['total_hate']) }}</p>
            <p class="metric-card-label">Terdeteksi Hate Speech</p>
        </div>
        <div class="metric-card-accent" style="background:var(--color-danger);"></div>
    </div>

    {{-- Non-Hate --}}
    <div class="metric-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;">
            <div class="metric-card-icon" style="background:var(--color-teal-bg);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-teal)" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="badge badge-safe" style="font-size:0.6875rem;">Non-Hate</span>
        </div>
        <div>
            <p class="metric-card-value" style="color:var(--color-teal);">{{ number_format($metrics['total_non_hate']) }}</p>
            <p class="metric-card-label">Bukan Ujaran Kebencian</p>
        </div>
        <div class="metric-card-accent" style="background:var(--color-teal);"></div>
    </div>

    {{-- Avg Hate % --}}
    <div class="metric-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;">
            <div class="metric-card-icon" style="background:var(--color-secondary-bg);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-secondary-dark)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
            <span class="badge" style="background:var(--color-secondary-bg);color:var(--color-secondary-dark);font-size:0.6875rem;">Rata-rata</span>
        </div>
        <div>
            <p class="metric-card-value" style="color:var(--color-secondary-dark);">{{ $metrics['avg_hate_pct'] }}<span style="font-size:1rem;">%</span></p>
            <p class="metric-card-label">Rata-rata Hate Speech</p>
        </div>
        <div class="metric-card-accent" style="background:var(--color-secondary);"></div>
    </div>

</div>

{{-- ── Charts Row ── --}}
<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:1.25rem;margin-bottom:1.75rem;" class="charts-row">

    {{-- Sentiment Donut --}}
    <div class="card" style="padding:1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <div>
                <p style="font-size:0.8125rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;">Rasio Sentimen</p>
                <p style="font-size:1rem;font-weight:700;color:var(--color-navy);">Hate vs Non-Hate</p>
            </div>
        </div>
        <div id="chart-sentiment-donut"></div>

        {{-- Legend --}}
        <div style="display:flex;gap:1.25rem;justify-content:center;margin-top:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <div style="width:10px;height:10px;border-radius:50%;background:var(--color-danger);"></div>
                <span style="font-size:0.8125rem;color:var(--color-text-muted);">Hate: <strong style="color:var(--color-text-body);">{{ $metrics['total_hate'] }}</strong></span>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <div style="width:10px;height:10px;border-radius:50%;background:var(--color-teal);"></div>
                <span style="font-size:0.8125rem;color:var(--color-text-muted);">Non-Hate: <strong style="color:var(--color-text-body);">{{ $metrics['total_non_hate'] }}</strong></span>
            </div>
        </div>
    </div>

    {{-- Level 2 Bar Chart --}}
    <div class="card" style="padding:1.5rem;">
        <div style="margin-bottom:1.25rem;">
            <p style="font-size:0.8125rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;">Distribusi Kategori</p>
            <p style="font-size:1rem;font-weight:700;color:var(--color-navy);">Sub-Kategori Hate Speech Level 2</p>
        </div>
        <div id="chart-level2-bar"></div>
    </div>

</div>

{{-- ── Platform + Recent ── --}}
<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.25rem;" class="bottom-row">

    {{-- Platform Donut --}}
    <div class="card" style="padding:1.5rem;">
        <div style="margin-bottom:1.25rem;">
            <p style="font-size:0.8125rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;">Platform</p>
            <p style="font-size:1rem;font-weight:700;color:var(--color-navy);">X vs Threads</p>
        </div>
        <div id="chart-platform"></div>
        <div style="display:flex;gap:1rem;justify-content:center;margin-top:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <div style="width:10px;height:10px;border-radius:50%;background:#1DA1F2;"></div>
                <span style="font-size:0.8125rem;color:var(--color-text-muted);">X (Twitter)</span>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <div style="width:10px;height:10px;border-radius:50%;background:#1E3A4C;"></div>
                <span style="font-size:0.8125rem;color:var(--color-text-muted);">Threads</span>
            </div>
        </div>
    </div>

    {{-- Recent Analyses Table --}}
    <div class="card" style="overflow:hidden;">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;">
            <p style="font-size:1rem;font-weight:700;color:var(--color-navy);">Analisis Terbaru</p>
            <a href="{{ route('analyses.index') }}" class="btn btn-ghost btn-sm">Lihat Semua →</a>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
                    <tr>
                        <th style="padding:0.625rem 1rem;font-size:0.75rem;font-weight:700;color:var(--color-text-muted);text-align:left;text-transform:uppercase;letter-spacing:0.05em;">Judul</th>
                        <th style="padding:0.625rem 1rem;font-size:0.75rem;font-weight:700;color:var(--color-text-muted);text-align:left;text-transform:uppercase;letter-spacing:0.05em;">Platform</th>
                        <th style="padding:0.625rem 1rem;font-size:0.75rem;font-weight:700;color:var(--color-text-muted);text-align:center;text-transform:uppercase;letter-spacing:0.05em;">Data</th>
                        <th style="padding:0.625rem 1rem;font-size:0.75rem;font-weight:700;color:var(--color-text-muted);text-align:center;text-transform:uppercase;letter-spacing:0.05em;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentAnalyses as $analysis)
                    <tr style="border-bottom:1px solid var(--color-border);transition:background 0.15s;"
                        onmouseover="this.style.background='var(--color-surface-2)'"
                        onmouseout="this.style.background=''">
                        <td style="padding:0.875rem 1rem;">
                            <a href="{{ route('analyses.show', $analysis->id) }}" style="font-size:0.875rem;font-weight:600;color:var(--color-navy);text-decoration:none;display:block;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                               onmouseover="this.style.color='var(--color-primary)'"
                               onmouseout="this.style.color='var(--color-navy)'">
                                {{ $analysis->title }}
                            </a>
                            <p style="font-size:0.75rem;color:var(--color-text-muted);margin-top:2px;">{{ $analysis->created_at->diffForHumans() }}</p>
                        </td>
                        <td style="padding:0.875rem 1rem;">
                            <span style="font-size:0.8125rem;font-weight:600;color:var(--color-text-body);">
                                {{ strtoupper($analysis->platform) }}
                            </span>
                        </td>
                        <td style="padding:0.875rem 1rem;text-align:center;">
                            <span style="font-size:0.875rem;font-weight:700;color:var(--color-navy);">
                                {{ $analysis->statistic ? number_format($analysis->statistic->total_data) : '—' }}
                            </span>
                        </td>
                        <td style="padding:0.875rem 1rem;text-align:center;">
                            @if($analysis->status === 'completed')
                                <span class="badge badge-safe">Selesai</span>
                            @elseif($analysis->status === 'running')
                                <span class="badge badge-warning" style="animation:pulse-orange 2s infinite;">Berjalan</span>
                            @elseif($analysis->status === 'failed')
                                <span class="badge badge-hate">Gagal</span>
                            @else
                                <span class="badge" style="background:var(--color-surface-2);color:var(--color-text-muted);">Antrian</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="padding:3rem 1rem;text-align:center;">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;">
                                <div style="width:48px;height:48px;border-radius:50%;background:var(--color-surface-2);display:flex;align-items:center;justify-content:center;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                </div>
                                <p style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">Belum ada analisis</p>
                                <p style="font-size:0.8125rem;color:var(--color-text-subtle);">Mulai analisis pertama Anda sekarang.</p>
                                @can('run-analysis')
                                <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-sm" style="margin-top:0.25rem;">+ Analisis Baru</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforelse
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

    // ── Sentiment Donut ──────────────────────────────────────────────
    new ApexCharts(document.getElementById('chart-sentiment-donut'), {
        series: hasData ? sentimentData.series : [1, 1],
        chart: { type: 'donut', height: 200, sparkline: { enabled: true } },
        labels: sentimentData.labels,
        colors: hasData ? sentimentData.colors : ['#E8DDD0', '#E8DDD0'],
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: hasData,
                        value: { fontSize: '1.25rem', fontWeight: 800, color: '#1E3A4C', fontFamily: 'Plus Jakarta Sans, sans-serif' },
                        total: {
                            show: true,
                            label: hasData ? 'Total' : 'Belum Ada Data',
                            fontSize: '0.75rem',
                            fontWeight: 600,
                            color: '#78716C',
                            fontFamily: 'Plus Jakarta Sans, sans-serif',
                            formatter: (w) => hasData ? w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('id') : '—'
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: { style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } }
    }).render();

    // ── Level 2 Bar Chart ────────────────────────────────────────────
    new ApexCharts(document.getElementById('chart-level2-bar'), {
        series: level2Data.series,
        chart: { type: 'bar', height: 220, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '65%' } },
        xaxis: { categories: level2Data.categories, labels: { style: { fontSize: '0.75rem', fontFamily: 'Plus Jakarta Sans, sans-serif', colors: '#78716C' } } },
        yaxis: { labels: { style: { fontSize: '0.72rem', fontFamily: 'Plus Jakarta Sans, sans-serif', colors: '#78716C' } } },
        colors: ['#E76F51'],
        dataLabels: { enabled: false },
        grid: { strokeDashArray: 4, borderColor: '#E8DDD0' },
        tooltip: { style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } }
    }).render();

    // ── Platform Donut ───────────────────────────────────────────────
    new ApexCharts(document.getElementById('chart-platform'), {
        series: platformData.series.some(v => v > 0) ? platformData.series : [1, 1],
        chart: { type: 'donut', height: 180, sparkline: { enabled: true } },
        labels: platformData.labels,
        colors: ['#1DA1F2', '#1E3A4C'],
        plotOptions: {
            pie: { donut: { size: '65%', labels: { show: false } } }
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: { style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } }
    }).render();
});
</script>

<style>
@media (max-width: 1024px) {
    .charts-row, .bottom-row { grid-template-columns: 1fr !important; }
}
</style>
@endpush
