<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FastAPIClientService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.fastapi.url', env('FASTAPI_BASE_URL', 'http://127.0.0.1:8080/api/v1')), '/');
        $this->timeout = (int) config('services.fastapi.timeout', env('FASTAPI_TIMEOUT', 10));
    }

    /**
     * Helper untuk membuat request HTTP client dengan connect timeout singkat.
     */
    protected function client(?int $customTimeout = null)
    {
        return Http::baseUrl($this->baseUrl)
            ->connectTimeout(3)
            ->timeout($customTimeout ?? $this->timeout)
            ->acceptJson();
    }

    /**
     * Mengekstrak pesan error ramah dari response FastAPI.
     */
    protected function extractErrorMessage(Response $response, string $fallback = 'Terjadi kesalahan pada server AI.'): string
    {
        $detail = $response->json('detail');
        if (is_string($detail)) {
            return $detail;
        }
        if (is_array($detail)) {
            $messages = [];
            foreach ($detail as $err) {
                if (isset($err['loc'], $err['msg'])) {
                    $field = end($err['loc']);
                    $messages[] = "Field '{$field}': {$err['msg']}";
                } elseif (isset($err['msg'])) {
                    $messages[] = $err['msg'];
                }
            }
            if (!empty($messages)) {
                return implode(', ', $messages);
            }
            return json_encode($detail);
        }
        return $response->json('message') ?? $fallback;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Modul Autentikasi & Sesi
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Memeriksa status sesi media sosial X dan Threads.
     */
    public function getAuthStatus(): array
    {
        try {
            $response = $this->client(2)->get('/auth/status');
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $response->json('detail') ?? 'Gagal mengambil status sesi dari server AI.',
            ];
        } catch (Exception $e) {
            Log::debug("FastAPI getAuthStatus error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Server AI Python tidak dapat dihubungi: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Memeriksa apakah server FastAPI AI sedang aktif dan merespons.
     */
    public function checkHealth(): bool
    {
        $status = $this->getAuthStatus();
        return (bool) ($status['success'] ?? false);
    }

    /**
     * Mengambil payload telemetri lengkap untuk antarmuka monitor scraper.
     */
    public function getTelemetryData(): array
    {
        $statusResult   = $this->getAuthStatus();
        $isServerOnline = (bool) ($statusResult['success'] ?? false);
        $sessionData    = $statusResult['data'] ?? null;
        $gatewayUrl     = $this->baseUrl;

        return [
            'isServerOnline' => $isServerOnline,
            'sessionData'    => $sessionData,
            'statusResult'   => $statusResult,
            'gatewayUrl'     => $gatewayUrl,
        ];
    }

    /**
     * Memicu login interaktif Playwright di server Python.
     */
    public function triggerLogin(string $platform): array
    {
        try {
            $response = $this->client()->post("/auth/login-trigger/{$platform}");
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $response->json('detail') ?? 'Gagal memicu proses login.',
            ];
        } catch (Exception $e) {
            Log::error("FastAPI triggerLogin error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal terhubung ke server AI: ' . $e->getMessage(),
            ];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Modul Preprocessing Teks
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Membersihkan dan menormalisasi satu string teks.
     */
    public function preprocessSingle(string $text): array
    {
        try {
            $response = $this->client()->post('/preprocess/single', ['text' => $text]);
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $response->json('detail') ?? 'Gagal memproses teks.',
            ];
        } catch (Exception $e) {
            Log::error("FastAPI preprocessSingle error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Membersihkan dan menormalisasi batch teks.
     */
    public function preprocessBatch(array $texts): array
    {
        try {
            $response = $this->client()->post('/preprocess/batch', ['texts' => $texts]);
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $response->json('detail') ?? 'Gagal memproses batch teks.',
            ];
        } catch (Exception $e) {
            Log::error("FastAPI preprocessBatch error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Modul Klasifikasi IndoBERT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Mengambil metadata model IndoBERT yang aktif.
     */
    public function getModelInfo(): array
    {
        try {
            $response = $this->client()->get('/classify/info');
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $response->json('detail') ?? 'Model IndoBERT belum siap.',
            ];
        } catch (Exception $e) {
            Log::error("FastAPI getModelInfo error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Melakukan klasifikasi satu teks kalimat.
     * Timeout lebih besar (60s) untuk menangani cold-start model IndoBERT.
     */
    public function classifySingle(string $text, bool $preprocess = true): array
    {
        try {
            // Gunakan timeout 60 detik khusus untuk inferensi model (cold-start bisa lambat)
            $response = $this->client(60)->post('/classify/single', [
                'text'       => $text,
                'preprocess' => $preprocess,
            ]);
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $this->extractErrorMessage($response, 'Gagal melakukan klasifikasi teks.'),
            ];
        } catch (Exception $e) {
            Log::error("FastAPI classifySingle error: " . $e->getMessage());
            // Pesan error yang lebih ramah pengguna
            $msg = $e->getMessage();
            if (str_contains($msg, 'timed out') || str_contains($msg, 'Operation timed out')) {
                return ['success' => false, 'message' => 'Server AI membutuhkan waktu terlalu lama untuk merespons. Pastikan FastAPI sudah sepenuhnya siap (model telah ter-load) dan coba lagi.'];
            }
            if (str_contains($msg, 'Connection refused') || str_contains($msg, 'Failed to connect')) {
                return ['success' => false, 'message' => 'Server AI tidak dapat dihubungi di port 8080. Pastikan uvicorn/FastAPI sedang berjalan.'];
            }
            return ['success' => false, 'message' => 'Kesalahan komunikasi dengan server AI: ' . $msg];
        }
    }

    /**
     * Melakukan klasifikasi batch teks.
     */
    public function classifyBatch(array $texts, bool $preprocess = true): array
    {
        try {
            $response = $this->client()->post('/classify/batch', [
                'texts'      => $texts,
                'preprocess' => $preprocess,
            ]);
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $response->json('detail') ?? 'Gagal melakukan klasifikasi batch.',
            ];
        } catch (Exception $e) {
            Log::error("FastAPI classifyBatch error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Modul Pipeline End-to-End
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Memulai job analisis penuh end-to-end secara asinkron.
     */
    public function runPipeline(array $params): array
    {
        try {
            $payload = [
                'keywords'         => $params['keywords'] ?? [],
                'platform'         => $params['platform'] ?? 'both',
                'search_mode'      => $params['search_mode'] ?? 'latest',
                'max_links'        => (int) ($params['max_links'] ?? 50),
                'max_scroll_steps' => (int) ($params['max_scroll_steps'] ?? 300),
                'headless'         => (bool) ($params['headless'] ?? false),
                'export_csv'       => true,
            ];

            $response = $this->client()->post('/pipeline/run', $payload);
            if ($response->status() === 202 || $response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }
            return [
                'success' => false,
                'message' => $this->extractErrorMessage($response, 'Gagal membuat job analisis pipeline.'),
            ];
        } catch (Exception $e) {
            Log::error("FastAPI runPipeline error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mengambil status progress dan statistik dari pipeline job.
     * Return includes 'http_status' sehingga caller bisa bedakan
     * 404 (job hilang karena server restart) vs error jaringan.
     */
    public function getPipelineStatus(string $jobId): array
    {
        try {
            $response = $this->client()->get("/pipeline/status/{$jobId}");
            if ($response->successful()) {
                return [
                    'success'     => true,
                    'data'        => $response->json(),
                    'http_status' => $response->status(),
                ];
            }
            return [
                'success'     => false,
                'http_status' => $response->status(),
                'message'     => $this->extractErrorMessage($response, 'Job pipeline tidak ditemukan.'),
            ];
        } catch (Exception $e) {
            Log::error("FastAPI getPipelineStatus error: " . $e->getMessage());
            return [
                'success'     => false,
                'http_status' => 0,
                'message'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Mendapatkan daftar file CSV hasil ekspor.
     */
    public function getExportList(): array
    {
        try {
            $response = $this->client()->get('/pipeline/exports');
            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            }
            return ['success' => false, 'message' => 'Gagal mengambil daftar file ekspor.'];
        } catch (Exception $e) {
            Log::error("FastAPI getExportList error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mengambil URL download file ekspor.
     */
    public function getExportDownloadUrl(string $filename): string
    {
        return "{$this->baseUrl}/pipeline/exports/{$filename}";
    }
}
