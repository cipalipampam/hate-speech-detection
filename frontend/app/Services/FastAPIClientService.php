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
            $response = $this->client(6)->get('/auth/status');
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
        try {
            $response = $this->client(3)->get('/health');
            if ($response->successful()) {
                return true;
            }
        } catch (Exception $e) {
            // fallback ke getAuthStatus jika terjadi kendala
        }

        $status = $this->getAuthStatus();
        return (bool) ($status['success'] ?? false);
    }

    /**
     * Mengambil payload telemetri lengkap untuk antarmuka monitor scraper.
     */
    public function getTelemetryData(): array
    {
        $statusResult    = $this->getAuthStatus();
        $isServerOnline  = (bool) ($statusResult['success'] ?? false);
        $sessionData     = $this->sanitizeSessionData($statusResult['data'] ?? null);

        // Runtime & mode GUI login (novnc saat Docker, native saat lokal).
        // Menjadi sumber kebenaran tunggal untuk instruksi login di UI.
        $loginEnvironment = $statusResult['data']['login_environment'] ?? null;

        return [
            'isServerOnline'   => $isServerOnline,
            'sessionData'      => $sessionData,
            'loginEnvironment' => $loginEnvironment,
            'statusResult'     => $statusResult,
            'serviceChannel'   => 'Internal Microservice Bridge',
        ];
    }

    /**
     * Hapus path absolut filesystem dari data sesi (defense-in-depth agar tidak bocor ke UI).
     */
    private function sanitizeSessionData(?array $sessionData): ?array
    {
        if (empty($sessionData)) {
            return $sessionData;
        }

        // Regex untuk mendeteksi path absolut Windows maupun Unix/Linux
        $pathPattern = '/^([A-Za-z]:\\\\|\/[a-z])/';

        foreach (['x', 'threads'] as $platform) {
            if (!isset($sessionData[$platform])) {
                continue;
            }

            // Hapus path absolut dari field 'profile_path'
            if (isset($sessionData[$platform]['profile_path'])) {
                $raw = $sessionData[$platform]['profile_path'];
                if (preg_match($pathPattern, (string) $raw)) {
                    $sessionData[$platform]['profile_path'] = basename((string) $raw);
                }
            }

            // Hapus path absolut yang mungkin masih tersembunyi dalam field 'message'
            if (isset($sessionData[$platform]['message'])) {
                $sessionData[$platform]['message'] = preg_replace(
                    '/\s*Profil:\s*[^\s]+/i',
                    '',
                    (string) $sessionData[$platform]['message']
                );
                $sessionData[$platform]['message'] = trim((string) $sessionData[$platform]['message']);
            }
        }

        return $sessionData;
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
    // 2. Modul Preprocessing Teks — TIDAK DIPAKAI FRONTEND (wrapper dihapus; endpoint FastAPI tetap ada)
    // ──────────────────────────────────────────────────────────────────────────

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Modul Klasifikasi IndoBERT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Klasifikasi satu teks; timeout 60s untuk mengakomodasi cold-start model IndoBERT.
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
                return ['success' => false, 'message' => 'Server AI tidak dapat dihubungi. Pastikan subsistem pemrosesan AI telah aktif.'];
            }
            return ['success' => false, 'message' => 'Kesalahan komunikasi dengan server AI: ' . $msg];
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
     * Ambil status pipeline job; hasil menyertakan 'http_status' (pembeda 404 vs error jaringan).
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
     * Unduh CSV ekspor streaming ke $sink (opsi `sink` Guzzle, hemat memori); filename di-basename().
     */
    public function downloadExportToStream(string $filename, $sink): bool
    {
        $filename = basename($filename);

        try {
            $response = $this->client(120)
                ->withOptions(['sink' => $sink])
                ->get("/pipeline/exports/{$filename}");

            if ($response->successful()) {
                return true;
            }

            Log::warning("FastAPI downloadExportToStream gagal untuk {$filename}: HTTP " . $response->status());
            return false;
        } catch (Exception $e) {
            Log::error("FastAPI downloadExportToStream error untuk {$filename}: " . $e->getMessage());
            return false;
        }
    }
}

