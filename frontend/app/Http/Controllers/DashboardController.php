<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Services\AnalysisService;
use App\Services\DashboardMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardMetricsService $metricsService,
        protected AnalysisService $analysisService
    ) {}

    /**
     * Tampilkan halaman Dashboard utama atau kembalikan data metrik terkini via AJAX.
     */
    public function index(Request $request): View|JsonResponse
    {
        // 1. Sinkronisasi status analisis yang masih running / queued ke FastAPI
        if (Analysis::whereIn('status', ['running', 'queued'])->exists()) {
            $this->analysisService->syncRunningAnalyses();
        }

        $metrics        = $this->metricsService->getGlobalMetrics();
        $sentimentChart = $this->metricsService->getSentimentRatioChartData();
        $level2Chart    = $this->metricsService->getLevel2BreakdownChartData();
        $platformChart  = $this->metricsService->getPlatformComparisonChartData();
        $recentAnalyses = $this->metricsService->getRecentAnalyses(6);

        // Format recentAnalyses untuk JSON dan reaktivitas di frontend
        $formattedRecent = $recentAnalyses->map(function ($a) {
            return [
                'id'         => $a->id,
                'id_padded'  => '#' . str_pad($a->id, 4, '0', STR_PAD_LEFT),
                'title'      => $a->title,
                'platform'   => strtoupper($a->platform),
                'total_data' => $a->statistic ? number_format($a->statistic->total_data) : '—',
                'status'     => $a->status,
                'date_str'   => $a->created_at ? $a->created_at->format('Y-m-d H:i') . ' (' . $a->created_at->diffForHumans() . ')' : '—',
                'show_url'   => route('analyses.show', $a->id),
            ];
        });

        $stillHasRunning = Analysis::whereIn('status', ['running', 'queued'])->exists();

        // 2. Jika dipanggil melalui polling AJAX dari Overview
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'         => true,
                'has_running'     => $stillHasRunning,
                'table_html'      => view('dashboard.index', compact('recentAnalyses', 'metrics', 'sentimentChart', 'level2Chart', 'platformChart', 'stillHasRunning'))->fragment('recent-table'),
                'metrics'         => $metrics,
                'recentAnalyses'  => $formattedRecent,
                'sentimentChart'  => $sentimentChart,
                'level2Chart'     => $level2Chart,
                'platformChart'   => $platformChart,
            ]);
        }

        return view('dashboard.index', compact(
            'metrics',
            'sentimentChart',
            'level2Chart',
            'platformChart',
            'recentAnalyses',
            'formattedRecent',
            'stillHasRunning',
        ));
    }
}
