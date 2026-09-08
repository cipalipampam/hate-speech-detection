<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\AnalysisClassification;
use App\Models\AnalysisPost;
use App\Models\AnalysisStatistic;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * Mengambil ringkasan statistik global untuk ditampilkan pada widget dashboard utama.
     */
    public function getGlobalMetrics(): array
    {
        // Agregasi cepat dari analysis_statistics
        $stats = AnalysisStatistic::join('analyses', 'analyses.id', '=', 'analysis_statistics.analysis_id')
            ->where('analyses.status', 'completed')
            ->selectRaw('
                COUNT(analyses.id)                            AS total_analyses,
                COALESCE(SUM(analysis_statistics.total_data), 0)           AS total_opinions,
                COALESCE(SUM(analysis_statistics.hate_speech_count), 0)     AS total_hate,
                COALESCE(SUM(analysis_statistics.non_hate_speech_count), 0) AS total_non_hate,
                COALESCE(AVG(analysis_statistics.hate_speech_pct), 0)       AS avg_hate_pct
            ')
            ->first();

        $totalOpinions = (int) ($stats->total_opinions ?? 0);
        $totalHate = (int) ($stats->total_hate ?? 0);
        $totalNonHate = (int) ($stats->total_non_hate ?? 0);

        return [
            'total_analyses'  => (int) ($stats->total_analyses ?? 0),
            'total_opinions'  => $totalOpinions,
            'total_hate'      => $totalHate,
            'total_non_hate'  => $totalNonHate,
            'avg_hate_pct'    => round((float) ($stats->avg_hate_pct ?? 0), 2),
            'hate_percentage' => $totalOpinions > 0 ? round(($totalHate / $totalOpinions) * 100, 2) : 0.00,
        ];
    }

    /**
     * Format data untuk grafik Donut / Pie Chart rasio Hate Speech vs Non-Hate Speech.
     */
    public function getSentimentRatioChartData(): array
    {
        $metrics = $this->getGlobalMetrics();

        return [
            'labels' => ['Ujaran Kebencian (Hate Speech)', 'Bukan Ujaran Kebencian (Non-Hate)'],
            'series' => [$metrics['total_hate'], $metrics['total_non_hate']],
            'colors' => ['#E11D48', '#059669'], // Rose 600 & Emerald 600
        ];
    }

    /**
     * Format data untuk Bar Chart distribusi 6 sub-kategori Level 2.
     */
    public function getLevel2BreakdownChartData(): array
    {
        $categories = [
            'delegitimasi_institusi'  => 'Delegitimasi Institusi',
            'dehumanisasi'            => 'Dehumanisasi',
            'ajakan_kekerasan'        => 'Ajakan Kekerasan',
            'hoaks_pemicu_kebencian'  => 'Hoaks Pemicu Kebencian',
            'kutukan_agama_personal'  => 'Kutukan Agama & Personal',
            'tidak_relevan'           => 'Tidak Relevan / Netral',
        ];

        $counts = AnalysisClassification::select('label_lvl2', DB::raw('count(*) as total'))
            ->groupBy('label_lvl2')
            ->pluck('total', 'label_lvl2')
            ->toArray();

        $labels = [];
        $data = [];

        foreach ($categories as $key => $humanLabel) {
            $labels[] = $humanLabel;
            $val = $counts[$key] ?? 0;
            if ($key === 'hoaks_pemicu_kebencian' && isset($counts['hoax_pemicu_kebencian'])) {
                $val += $counts['hoax_pemicu_kebencian'];
            }
            $data[] = $val;
        }

        return [
            'categories' => $labels,
            'series'     => [
                [
                    'name' => 'Jumlah Postingan',
                    'data' => $data,
                ],
            ],
            'raw_counts' => $counts,
        ];
    }

    /**
     * Format data untuk grafik perbandingan platform (X vs Threads).
     */
    public function getPlatformComparisonChartData(): array
    {
        // 1. Agregasi dari analysis_posts (dengan fallback ke platform analisis jika unknown)
        $platformCounts = DB::table('analysis_posts')
            ->leftJoin('analyses', 'analyses.id', '=', 'analysis_posts.analysis_id')
            ->selectRaw("LOWER(COALESCE(NULLIF(analysis_posts.platform, 'Unknown'), analyses.platform, 'unknown')) as plat, COUNT(analysis_posts.id) as total")
            ->groupBy('plat')
            ->pluck('total', 'plat')
            ->toArray();

        $xCount = (int) (($platformCounts['x'] ?? 0) + ($platformCounts['twitter'] ?? 0));
        $threadsCount = (int) ($platformCounts['threads'] ?? 0);

        // 2. Fallback ke analysis_statistics jika tabel analysis_posts kosong
        if ($xCount === 0 && $threadsCount === 0) {
            $analysisStats = DB::table('analyses')
                ->leftJoin('analysis_statistics', 'analyses.id', '=', 'analysis_statistics.analysis_id')
                ->where('analyses.status', 'completed')
                ->selectRaw("LOWER(analyses.platform) as plat, COALESCE(SUM(analysis_statistics.total_data), 0) as total")
                ->groupBy('plat')
                ->pluck('total', 'plat')
                ->toArray();

            $xCount = (int) (($analysisStats['x'] ?? 0) + ($analysisStats['twitter'] ?? 0));
            $threadsCount = (int) ($analysisStats['threads'] ?? 0);
        }

        $total = $xCount + $threadsCount;

        return [
            'labels'             => ['X (Twitter)', 'Threads (Meta)'],
            'series'             => [$xCount, $threadsCount],
            'colors'             => ['#0A0A0A', '#002FA7'],
            'total'              => $total,
            'x_count'            => $xCount,
            'threads_count'      => $threadsCount,
            'x_pct'              => $total > 0 ? round(($xCount / $total) * 100, 1) : 0,
            'threads_pct'        => $total > 0 ? round(($threadsCount / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Mengambil daftar analisis terbaru untuk ditampilkan pada tabel dashboard.
     */
    public function getRecentAnalyses(int $limit = 5)
    {
        return Analysis::with(['user', 'statistic', 'exports'])
            ->latest()
            ->take($limit)
            ->get();
    }
}
