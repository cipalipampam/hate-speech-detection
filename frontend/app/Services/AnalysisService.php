<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\AnalysisClassification;
use App\Models\AnalysisExport;
use App\Models\AnalysisPost;
use App\Models\AnalysisStatistic;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service: AnalysisService
 *
 * Tanggung Jawab:
 * - Membuat sesi analisis & memicu job di FastAPI.
 * - Menyinkronkan status job dari FastAPI ke DB MySQL.
 * - Bulk insert postingan ke analysis_posts + analysis_classifications.
 * - Menyimpan ringkasan metrik ke analysis_statistics.
 * - Menghitung statistik global untuk Dashboard.
 */
class AnalysisService
{
    public function __construct(
        protected FastAPIClientService $fastApiClient
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Memulai Sesi Analisis
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Buat record analisis & trigger background job di FastAPI.
     */
    public function startAnalysis(array $data, int $userId): Analysis
    {
        // 1. Simpan konfigurasi job ke DB
        $analysis = Analysis::create([
            'user_id'          => $userId,
            'title'            => $data['title'],
            'platform'         => $data['platform'],
            'keywords'         => $data['keywords'],
            'search_mode'      => $data['search_mode'],
            'max_links'        => $data['max_links'] ?? 50,
            'max_scroll_steps' => $data['max_scroll_steps'] ?? 300,
            'headless'         => $data['headless'] ?? false,
            'status'           => 'queued',
        ]);

        // 2. Kirim job ke FastAPI
        $apiResult = $this->fastApiClient->runPipeline([
            'keywords'         => $data['keywords'],
            'platform'         => $data['platform'],
            'search_mode'      => $data['search_mode'],
            'max_links'        => $data['max_links'] ?? 50,
            'max_scroll_steps' => $data['max_scroll_steps'] ?? 300,
            'headless'         => $data['headless'] ?? false,
        ]);

        if ($apiResult['success'] && isset($apiResult['data']['job_id'])) {
            $analysis->update([
                'job_id' => $apiResult['data']['job_id'],
                'status' => 'running',
            ]);
        } else {
            $analysis->update([
                'status'        => 'failed',
                'error_message' => $apiResult['message'] ?? 'Gagal memicu job di server AI.',
            ]);
        }

        return $analysis;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Sinkronisasi Status Job dari FastAPI
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Polling status dari FastAPI dan sinkronkan ke semua tabel terkait.
     */
    public function syncAnalysisStatus(Analysis $analysis): Analysis
    {
        if (in_array($analysis->status, ['completed', 'failed']) || empty($analysis->job_id)) {
            return $analysis;
        }

        $statusResult = $this->fastApiClient->getPipelineStatus($analysis->job_id);

        if (!$statusResult['success']) {
            return $analysis;
        }

        $jobData = $statusResult['data'];
        $apiStatus = $jobData['status'] ?? 'running';

        if ($apiStatus === 'success') {
            $this->handleJobSuccess($analysis, $jobData);
        } elseif ($apiStatus === 'error') {
            $analysis->update([
                'status'                 => 'failed',
                'execution_time_seconds' => $jobData['elapsed_seconds'] ?? null,
                'error_message'          => $jobData['error_detail'] ?? ($jobData['message'] ?? 'Pipeline error.'),
            ]);
        }

        return $analysis->fresh();
    }

    /**
     * Proses saat job FastAPI selesai dengan sukses.
     */
    protected function handleJobSuccess(Analysis $analysis, array $jobData): void
    {
        $stats = $jobData['statistics'] ?? [];

        // a. Update status & execution time di tabel analyses
        $analysis->update([
            'status'                 => 'completed',
            'execution_time_seconds' => $jobData['elapsed_seconds'] ?? null,
        ]);

        // b. Simpan ringkasan metrik ke analysis_statistics (insert or update)
        AnalysisStatistic::updateOrCreate(
            ['analysis_id' => $analysis->id],
            [
                'total_data'            => $stats['total_data'] ?? ($jobData['total_data'] ?? 0),
                'hate_speech_count'     => $stats['hate_speech_count'] ?? 0,
                'non_hate_speech_count' => $stats['non_hate_speech_count'] ?? 0,
                'hate_speech_pct'       => $stats['hate_speech_pct'] ?? 0.00,
                'avg_confidence_lvl1'   => $stats['avg_confidence_lvl1'] ?? 0.00,
                'avg_confidence_lvl2'   => $stats['avg_confidence_lvl2'] ?? 0.00,
                'level2_breakdown'      => $stats['level2_breakdown'] ?? [],
                'platform_breakdown'    => $stats['platform_breakdown'] ?? [],
            ]
        );

        // c. Catat file ekspor ke analysis_exports
        if (!empty($jobData['exported_file'])) {
            $backendExportPath = base_path('../backend/storage/exports/' . $jobData['exported_file']);
            $sizeBytes = file_exists($backendExportPath) ? filesize($backendExportPath) : 0;

            AnalysisExport::firstOrCreate(
                [
                    'analysis_id' => $analysis->id,
                    'filename'    => $jobData['exported_file'],
                ],
                [
                    'disk'       => 'backend',
                    'path'       => 'storage/exports/' . $jobData['exported_file'],
                    'format'     => 'csv',
                    'size_bytes' => $sizeBytes,
                    'mime_type'  => 'text/csv',
                ]
            );
        }

        // d. Import postingan dari CSV ke analysis_posts + analysis_classifications
        if (!empty($jobData['exported_file'])) {
            $this->importPostsFromCsv($analysis, $jobData['exported_file']);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Import Postingan dari CSV ke Database
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Membaca file CSV ekspor dari backend Python dan menyimpan ke 2 tabel:
     * analysis_posts (metadata) dan analysis_classifications (konten + label).
     */
    protected function importPostsFromCsv(Analysis $analysis, string $csvFilename): void
    {
        $csvPath = base_path('../backend/storage/exports/' . $csvFilename);

        if (!file_exists($csvPath) || !is_readable($csvPath)) {
            Log::warning("CSV ekspor tidak ditemukan: {$csvPath}");
            return;
        }

        // Jangan import ulang jika sudah ada data
        if ($analysis->posts()->exists()) {
            return;
        }

        try {
            $file = fopen($csvPath, 'r');
            $header = fgetcsv($file);

            if (!$header) {
                fclose($file);
                return;
            }

            $postsBatch = [];
            $classificationsBatch = [];
            $batchSize = 200;
            $now = now()->toDateTimeString();

            while (($row = fgetcsv($file)) !== false) {
                if (count($row) !== count($header)) {
                    continue;
                }

                $d = array_combine($header, $row);

                // Insert ke analysis_posts (metadata ringan)
                $postsBatch[] = [
                    'analysis_id'     => $analysis->id,
                    'platform'        => $d['platform'] ?? 'Unknown',
                    'source_url'      => $d['source'] ?? ($d['source_thread'] ?? null),
                    'author_username' => $d['user_id'] ?? ($d['username'] ?? 'anonymous'),
                    'post_type'       => $d['type'] ?? 'Original Post',
                    'post_date'       => $d['date'] ?? null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];

                // Simpan konten & klasifikasi terpisah (dikaitkan via post_id setelah insert)
                $classificationsBatch[] = [
                    'raw_content'        => $d['content'] ?? ($d['raw_text'] ?? ''),
                    'clean_content'      => $d['clean_text'] ?? ($d['content'] ?? ''),
                    'label_lvl1'         => $d['label_lvl1'] ?? 'non_hate_speech',
                    'confidence_lvl1'    => (float) ($d['confidence_lvl1'] ?? 0.0),
                    'probabilities_lvl1' => null,
                    'label_lvl2'         => $d['label_lvl2'] ?? 'tidak_relevan',
                    'confidence_lvl2'    => (float) ($d['confidence_lvl2'] ?? 0.0),
                    'probabilities_lvl2' => null,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];

                if (count($postsBatch) >= $batchSize) {
                    $this->flushBatch($postsBatch, $classificationsBatch);
                    $postsBatch = [];
                    $classificationsBatch = [];
                }
            }

            if (!empty($postsBatch)) {
                $this->flushBatch($postsBatch, $classificationsBatch);
            }

            fclose($file);
            Log::info("Import CSV selesai untuk Analisis #{$analysis->id}");
        } catch (Exception $e) {
            Log::error("Gagal import CSV Analisis #{$analysis->id}: " . $e->getMessage());
        }
    }

    /**
     * Flush satu batch ke analysis_posts, ambil ID-nya, lalu insert analysis_classifications.
     */
    protected function flushBatch(array $postsBatch, array $classificationsBatch): void
    {
        // Insert metadata posts, ambil ID yang di-generate
        $firstPostId = DB::table('analysis_posts')->insertGetId($postsBatch[0]);
        $offset = $firstPostId;

        // Insert sisa batch
        if (count($postsBatch) > 1) {
            DB::table('analysis_posts')->insert(array_slice($postsBatch, 1));
        }

        // Cocokkan post_id ke setiap classification
        for ($i = 0; $i < count($classificationsBatch); $i++) {
            $classificationsBatch[$i]['post_id'] = $offset + $i;
        }

        DB::table('analysis_classifications')->insert($classificationsBatch);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Statistik Global Dashboard
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Menghitung metrik agregasi global untuk halaman Dashboard utama.
     * Hanya query tabel analysis_statistics (ringan, tidak JOIN ke tabel besar).
     */
    public function getGlobalDashboardStats(): array
    {
        $stats = AnalysisStatistic::join('analyses', 'analyses.id', '=', 'analysis_statistics.analysis_id')
            ->where('analyses.status', 'completed')
            ->selectRaw('
                COUNT(analyses.id)                    AS total_analyses,
                SUM(analysis_statistics.total_data)   AS total_opinions,
                SUM(analysis_statistics.hate_speech_count)     AS total_hate,
                SUM(analysis_statistics.non_hate_speech_count) AS total_non_hate,
                AVG(analysis_statistics.hate_speech_pct)       AS avg_hate_pct
            ')
            ->first();

        // Distribusi Label Level 2 (dari classifications — query ini jarang dipanggil)
        $lvl2Distribution = AnalysisClassification::select('label_lvl2', DB::raw('count(*) as count'))
            ->groupBy('label_lvl2')
            ->orderByDesc('count')
            ->pluck('count', 'label_lvl2')
            ->toArray();

        // Distribusi Platform (dari posts — ringan karena tidak ada teks)
        $platformDistribution = AnalysisPost::select('platform', DB::raw('count(*) as count'))
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();

        return [
            'total_analyses'        => (int) ($stats->total_analyses ?? 0),
            'total_opinions'        => (int) ($stats->total_opinions ?? 0),
            'total_hate'            => (int) ($stats->total_hate ?? 0),
            'total_non_hate'        => (int) ($stats->total_non_hate ?? 0),
            'avg_hate_pct'          => round((float) ($stats->avg_hate_pct ?? 0), 2),
            'level2_distribution'   => $lvl2Distribution,
            'platform_distribution' => $platformDistribution,
            'recent_analyses'       => Analysis::with(['user', 'statistic'])
                                               ->latest()
                                               ->take(5)
                                               ->get(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Query Helper untuk Detail Analisis
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Mengambil semua postingan suatu analisis dengan eager-loading klasifikasi.
     * Mendukung filter platform, label_lvl1, dan label_lvl2.
     */
    public function getAnalysisPosts(
        Analysis $analysis,
        ?string $platform = null,
        ?string $labelLvl1 = null,
        ?string $labelLvl2 = null,
        int $perPage = 50
    ) {
        $query = AnalysisPost::with('classification')
            ->where('analysis_id', $analysis->id);

        if ($platform) {
            $query->where('platform', $platform);
        }

        if ($labelLvl1 || $labelLvl2) {
            $query->whereHas('classification', function ($q) use ($labelLvl1, $labelLvl2) {
                if ($labelLvl1) {
                    $q->where('label_lvl1', $labelLvl1);
                }
                if ($labelLvl2) {
                    $q->where('label_lvl2', $labelLvl2);
                }
            });
        }

        return $query->paginate($perPage);
    }
}
