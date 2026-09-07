<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\AnalysisPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AnalysisService
{
    public function __construct(
        protected FastAPIClientService $fastApiClient,
        protected AnalysisImportService $importService
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Eksekusi & Lifecycle Sesi Analisis
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Membuat record analisis baru di database dan mengirim job ke server AI FastAPI.
     */
    public function startAnalysis(array $data, int $userId): Analysis
    {
        // 1. Simpan konfigurasi awal ke tabel analyses
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

        // 2. Kirim request asynchronous ke FastAPI
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
            // Pastikan error message tidak pernah kosong
            $errMsg = $apiResult['message']
                ?? 'Server AI Python tidak dapat dihubungi. Pastikan FastAPI berjalan di port 8080.';

            // Cukup tunjukkan pesan yang mudah dibaca, potong jika terlalu panjang
            if (strlen($errMsg) > 300) {
                $errMsg = 'Server AI Python tidak dapat dihubungi. Pastikan FastAPI berjalan di port 8080.';
            }

            $analysis->update([
                'status'        => 'failed',
                'error_message' => $errMsg,
            ]);
        }

        return $analysis;
    }


    /**
     * Memeriksa status terbaru dari FastAPI dan menyinkronkan data via AnalysisImportService.
     */
    public function syncAnalysisStatus(Analysis $analysis): Analysis
    {
        // Jika sudah completed, tidak perlu sync ulang
        if ($analysis->status === 'completed') {
            return $analysis;
        }

        // Jika tidak memiliki job_id dari server AI, tandai gagal
        if (empty($analysis->job_id)) {
            $analysis->update([
                'status'        => 'failed',
                'error_message' => $analysis->error_message ?? 'Sesi analisis tidak memiliki ID pekerjaan (job_id) di server AI.',
            ]);
            return $analysis;
        }

        $statusResult = $this->fastApiClient->getPipelineStatus($analysis->job_id);

        if (!$statusResult['success']) {
            $httpStatus = $statusResult['http_status'] ?? 0;

            // Hanya tandai FAILED jika server AI secara eksplisit mengembalikan 404 (Job Not Found)
            if ($httpStatus === 404) {
                $analysis->update([
                    'status'        => 'failed',
                    'error_message' => 'Job analisis tidak ditemukan di server AI (kemungkinan server direstart). Silakan buat analisis baru.',
                ]);
            }
            // Jika http_status === 0 (timeout sementara saat CPU sibuk klasifikasi IndoBERT):
            // JANGAN matikan analisis! Tetap biarkan berjalan agar pengecekan berikutnya dapat mengambil hasil.
            return $analysis->fresh();
        }

        $jobData   = $statusResult['data'];
        $apiStatus = $jobData['status'] ?? 'running';

        if ($apiStatus === 'success') {
            // Serahkan proses parsing dan penyimpanan ke AnalysisImportService
            $this->importService->importFromFastApiResult($analysis, $jobData);
        } elseif ($apiStatus === 'error' || $apiStatus === 'failed') {
            $analysis->update([
                'status'                 => 'failed',
                'execution_time_seconds' => $jobData['elapsed_seconds'] ?? null,
                'error_message'          => $jobData['error_detail'] ?? ($jobData['message'] ?? 'Terjadi kesalahan saat pemrosesan pipeline.'),
            ]);
        }
        // Jika status 'running' atau 'queued' → biarkan tetap berjalan

        $fresh = $analysis->fresh(['statistic', 'exports']);
        if ($fresh) {
            $msg = $jobData['message'] ?? null;
            if ($msg) {
                // Filter pesan teknis polling agar tidak membingungkan pengguna
                if (stripos($msg, 'polling') !== false || stripos($msg, 'asinkron') !== false) {
                    $msg = 'Pipeline analisis sedang diproses oleh sistem...';
                }
            }
            $fresh->setAttribute('pipeline_message', $msg);

            $step = 1;
            if ($fresh->status === 'completed') {
                $step = 4;
            } elseif ($msg) {
                $m = strtolower($msg);
                if (str_contains($m, 'klasifikasi') || str_contains($m, 'indobert') || str_contains($m, 'inferensi') || str_contains($m, 'langkah 4') || str_contains($m, 'langkah 5')) {
                    $step = 4;
                } elseif (str_contains($m, 'preprocess') || str_contains($m, 'kamusalay') || str_contains($m, 'pembersihan') || str_contains($m, 'normalisasi') || str_contains($m, 'langkah 3')) {
                    $step = 3;
                } elseif (str_contains($m, 'scraping') || str_contains($m, 'crawling') || str_contains($m, 'meluncurkan') || str_contains($m, 'tweet') || str_contains($m, 'thread') || str_contains($m, 'unduh') || str_contains($m, 'langkah 2')) {
                    $step = 2;
                } elseif (str_contains($m, 'browser') || str_contains($m, 'playwright') || str_contains($m, 'validasi') || str_contains($m, 'inisialisasi') || str_contains($m, 'antrean') || str_contains($m, 'langkah 1')) {
                    $step = 1;
                }
            }
            $fresh->setAttribute('pipeline_step', $step);
            return $fresh;
        }

        return $analysis;
    }


    // ──────────────────────────────────────────────────────────────────────────
    // 2. Query Data & Filtering
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Mengambil daftar analisis dengan pagination dan eager loading.
     */
    public function getPaginatedAnalyses(
        ?int $userId = null,
        int $perPage = 10,
        ?string $search = null,
        ?string $platform = null,
        ?string $status = null
    ): LengthAwarePaginator {
        $query = Analysis::with(['user', 'statistic', 'exports'])->latest();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('keywords', 'like', "%{$search}%");
            });
        }

        if ($platform && strtolower($platform) !== 'all') {
            $query->where('platform', strtolower($platform));
        }

        if ($status && strtolower($status) !== 'all') {
            $query->where('status', strtolower($status));
        }

        return $query->paginate($perPage);
    }

    /**
     * Mengambil butir-butir postingan hasil analisis dengan filter relasional.
     */
    public function getFilteredPosts(Analysis $analysis, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = AnalysisPost::with('classification')
            ->where('analysis_id', $analysis->id);

        // Filter Platform (X vs Threads)
        if (!empty($filters['platform']) && strtolower($filters['platform']) !== 'all') {
            $query->where('platform', $filters['platform']);
        }

        // Filter Level 1 & Level 2 & Text Search pada Classification
        $labelLvl1 = $filters['label_lvl1'] ?? null;
        $labelLvl2 = $filters['label_lvl2'] ?? null;
        $search    = $filters['search'] ?? null;

        if (($labelLvl1 && $labelLvl1 !== 'all') || ($labelLvl2 && $labelLvl2 !== 'all') || $search) {
            $query->whereHas('classification', function ($q) use ($labelLvl1, $labelLvl2, $search) {
                if ($labelLvl1 && $labelLvl1 !== 'all') {
                    $q->where('label_lvl1', $labelLvl1);
                }
                if ($labelLvl2 && $labelLvl2 !== 'all') {
                    $q->where('label_lvl2', $labelLvl2);
                }
                if ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('raw_content', 'like', "%{$search}%")
                            ->orWhere('clean_content', 'like', "%{$search}%");
                    });
                }
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Menghapus sesi analisis beserta seluruh postingan, klasifikasi, dan file ekspor.
     */
    public function deleteAnalysis(Analysis $analysis): bool
    {
        return $analysis->delete();
    }
}
