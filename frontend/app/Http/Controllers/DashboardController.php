<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardMetricsService $metricsService
    ) {}

    /**
     * Tampilkan halaman Dashboard utama.
     */
    public function index(): View
    {
        $metrics       = $this->metricsService->getGlobalMetrics();
        $sentimentChart = $this->metricsService->getSentimentRatioChartData();
        $level2Chart    = $this->metricsService->getLevel2BreakdownChartData();
        $platformChart  = $this->metricsService->getPlatformComparisonChartData();
        $recentAnalyses = $this->metricsService->getRecentAnalyses(6);

        return view('dashboard.index', compact(
            'metrics',
            'sentimentChart',
            'level2Chart',
            'platformChart',
            'recentAnalyses',
        ));
    }
}
