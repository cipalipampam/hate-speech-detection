<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\AnalysisExport;
use App\Models\AnalysisStatistic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalysisImportService
{
    public function __construct(
        protected ?FastAPIClientService $fastApiClient = null
    ) {
        $this->fastApiClient = $fastApiClient ?? app(FastAPIClientService::class);
    }

    /**
     * Memproses hasil eksekusi FastAPI: simpan statistik, catat ekspor, dan impor postingan.
     */
    public function importFromFastApiResult(Analysis $analysis, array $jobData): void
    {
        $stats = $jobData['statistics'] ?? [];

        // 1. Update status job di tabel analyses
        $analysis->update([
            'status'                 => 'completed',
            'execution_time_seconds' => $jobData['elapsed_seconds'] ?? null,
            'error_message'          => null,
        ]);

        // 2. Simpan atau perbarui ringkasan agregasi di analysis_statistics
        AnalysisStatistic::updateOrCreate(
            ['analysis_id' => $analysis->id],
            [
                'total_data'            => $stats['total_data'] ?? ($jobData['total_data'] ?? 0),
                'hate_speech_count'     => $stats['hate_speech_count'] ?? 0,
                'non_hate_speech_count' => $stats['non_hate_speech_count'] ?? 0,
                'hate_speech_pct'       => $stats['hate_speech_pct'] ?? 0.00,
                'avg_confidence_lvl1'   => $stats['avg_confidence_lvl1'] ?? 0.0000,
                'avg_confidence_lvl2'   => $stats['avg_confidence_lvl2'] ?? 0.0000,
                'level2_breakdown'      => $stats['level2_breakdown'] ?? [],
                'platform_breakdown'    => $stats['platform_breakdown'] ?? [],
            ]
        );

        // 3. Catat metadata file ekspor di analysis_exports
        if (!empty($jobData['exported_file'])) {
            $this->recordExportFile($analysis, $jobData['exported_file']);

            // 4. Impor detail baris postingan dari file CSV (HTTP FastAPI / fallback filesystem)
            $this->importPostsFromCsv($analysis, $jobData['exported_file']);
        }
    }

    /**
     * Mencatat informasi file CSV hasil ekspor ke database.
     */
    public function recordExportFile(Analysis $analysis, string $filename, string $format = 'csv', int $sizeBytes = 0): AnalysisExport
    {
        return AnalysisExport::updateOrCreate(
            [
                'analysis_id' => $analysis->id,
                'filename'    => $filename,
            ],
            [
                'disk'       => 'backend',
                'path'       => 'storage/exports/' . $filename,
                'format'     => $format,
                'size_bytes' => $sizeBytes,
                'mime_type'  => $format === 'csv' ? 'text/csv' : 'application/octet-stream',
            ]
        );
    }

    /**
     * Membaca file CSV ekspor dan memasukkan ke analysis_posts + analysis_classifications
     * dengan aman menggunakan Database Transaction per chunk.
     * Sumber data: HTTP endpoint FastAPI (GET /api/v1/pipeline/exports/{filename}) — sama
     * untuk lingkungan lokal maupun Docker (tanpa akses path filesystem backend).
     */
    public function importPostsFromCsv(Analysis $analysis, string $filename): int
    {
        // Hindari duplikasi jika sudah pernah diimpor
        if ($analysis->posts()->exists()) {
            return $analysis->posts()->count();
        }

        // Decoupled penuh: SELALU ambil CSV via HTTP FastAPI, tanpa menyentuh filesystem
        // backend. Jalur lokal `base_path('../backend/storage/exports/...')` dihapus karena
        // melanggar prinsip anti-path-mismatch dan membuat perilaku lokal ≠ Docker.
        // Ditulis streaming ke php://temp (spill ke file setelah 2MB) supaya ekspor
        // berukuran besar tidak ditampung utuh di memori PHP.
        $file = fopen('php://temp', 'r+');

        if (! $this->fastApiClient->downloadExportToStream($filename, $file)) {
            fclose($file);
            Log::warning("File ekspor CSV gagal diambil via endpoint FastAPI: {$filename}");
            return 0;
        }

        $sizeBytes = (int) (fstat($file)['size'] ?? 0);
        rewind($file);

        // Perbarui ukuran file pada metadata ekspor jika sebelumnya tercatat 0
        if ($sizeBytes > 0) {
            AnalysisExport::where('analysis_id', $analysis->id)
                ->where('filename', $filename)
                ->where('size_bytes', 0)
                ->update(['size_bytes' => $sizeBytes]);
        }

        $header = fgetcsv($file);

        if (!$header) {
            fclose($file);
            return 0;
        }

        // Buang UTF-8 BOM dari sel pertama header.
        // Ekspor pipeline backend ditulis dengan encoding="utf-8-sig"
        // (lihat backend/src/pipeline/end_to_end_pipeline.py), sehingga nama kolom
        // pertama terbaca sebagai "\xEF\xBB\xBFplatform" — bukan "platform".
        // Akibatnya lookup $d['platform'] selalu gagal dan platform post hanya
        // ditebak dari source_url (bisa salah untuk analisis "both").
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        }

        $batchSize = 250;
        $batch = [];
        $totalImported = 0;

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }

            $batch[] = array_combine($header, $row);

            if (count($batch) >= $batchSize) {
                $totalImported += $this->insertBatchTransactional($analysis->id, $batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $totalImported += $this->insertBatchTransactional($analysis->id, $batch);
        }

        fclose($file);
        Log::info("Sukses mengimpor {$totalImported} postingan untuk Analisis ID #{$analysis->id}");

        return $totalImported;
    }

    /**
     * Memasukkan batch data berpasangan (Post + Classification) secara aman dalam transaksi DB.
     */
    protected function insertBatchTransactional(int $analysisId, array $rows): int
    {
        return DB::transaction(function () use ($analysisId, $rows) {
            $count = 0;
            $now = now()->toDateTimeString();

            // Diambil SEKALI di luar loop: nilai ini konstan untuk seluruh batch.
            // Sebelumnya query ini dieksekusi per baris (N+1) — 1 batch 250 baris
            // berarti 250 query identik ke tabel `analyses`.
            $parentPlatform = DB::table('analyses')->where('id', $analysisId)->value('platform') ?? 'threads';

            foreach ($rows as $d) {
                // Untuk analisis "both", platform per-post dideteksi dari source_url
                // agar filter platform pada halaman detail bisa bekerja dengan benar
                $csvPlatform = $d['platform'] ?? '';
                $sourceUrl   = $d['source'] ?? ($d['source_thread'] ?? '');

                if (!empty($csvPlatform) && strtolower($csvPlatform) !== 'unknown' && strtolower($csvPlatform) !== 'both') {
                    // CSV sudah punya nilai spesifik (X / Threads) — gunakan langsung
                    $platform = $csvPlatform;
                } elseif (!empty($sourceUrl)) {
                    // Deteksi dari source URL
                    $lowerUrl = strtolower($sourceUrl);
                    if (str_contains($lowerUrl, 'x.com') || str_contains($lowerUrl, 'twitter.com')) {
                        $platform = 'X';
                    } elseif (str_contains($lowerUrl, 'threads.net') || str_contains($lowerUrl, 'threads.com')) {
                        $platform = 'Threads';
                    } else {
                        $platform = ucfirst(strtolower($parentPlatform !== 'both' ? $parentPlatform : 'threads'));
                    }
                } else {
                    $platform = ucfirst(strtolower($parentPlatform !== 'both' ? $parentPlatform : 'threads'));
                }

                // 1. Insert ke analysis_posts (Metadata ringan)
                $postId = DB::table('analysis_posts')->insertGetId([
                    'analysis_id'     => $analysisId,
                    'platform'        => $platform,
                    'source_url'      => $d['source'] ?? ($d['source_thread'] ?? null),
                    'author_username' => $d['user_id'] ?? ($d['username'] ?? 'anonymous'),
                    'post_type'       => $d['type'] ?? 'Original Post',
                    'post_date'       => $d['date'] ?? null,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);

                $labelLvl1 = $d['label_lvl1'] ?? 'non_hate_speech';
                $labelLvl2 = ($labelLvl1 !== 'hate_speech') ? 'tidak_relevan' : ($d['label_lvl2'] ?? 'tidak_relevan');
                $confLvl1  = (float) ($d['confidence_lvl1'] ?? 0.0);
                $confLvl2  = ($labelLvl1 !== 'hate_speech') ? $confLvl1 : (float) ($d['confidence_lvl2'] ?? 0.0);

                // 2. Insert ke analysis_classifications (Konten teks & label hasil IndoBERT)
                DB::table('analysis_classifications')->insert([
                    'post_id'            => $postId,
                    'raw_content'        => $d['content'] ?? ($d['raw_text'] ?? ''),
                    'clean_content'      => $d['clean_text'] ?? ($d['content'] ?? ''),
                    'label_lvl1'         => $labelLvl1,
                    'confidence_lvl1'    => $confLvl1,
                    'probabilities_lvl1' => null,
                    'label_lvl2'         => $labelLvl2,
                    'confidence_lvl2'    => $confLvl2,
                    'probabilities_lvl2' => null,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);

                $count++;
            }

            return $count;
        });
    }
}
