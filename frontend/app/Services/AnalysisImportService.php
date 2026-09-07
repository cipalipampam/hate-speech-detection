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

class AnalysisImportService
{
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

            // 4. Impor detail baris postingan dari file CSV
            $this->importPostsFromCsv($analysis, $jobData['exported_file']);
        }
    }

    /**
     * Mencatat informasi file CSV hasil ekspor ke database.
     */
    public function recordExportFile(Analysis $analysis, string $filename, string $format = 'csv'): AnalysisExport
    {
        $backendExportPath = base_path('../backend/storage/exports/' . $filename);
        $sizeBytes = file_exists($backendExportPath) ? filesize($backendExportPath) : 0;

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
     */
    public function importPostsFromCsv(Analysis $analysis, string $filename): int
    {
        $csvPath = base_path('../backend/storage/exports/' . $filename);

        if (!file_exists($csvPath) || !is_readable($csvPath)) {
            Log::warning("File ekspor CSV tidak ditemukan: {$csvPath}");
            return 0;
        }

        // Hindari duplikasi jika sudah pernah diimpor
        if ($analysis->posts()->exists()) {
            return $analysis->posts()->count();
        }

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file);

        if (!$header) {
            fclose($file);
            return 0;
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

            foreach ($rows as $d) {
                // 1. Insert ke analysis_posts (Metadata ringan)
                $postId = DB::table('analysis_posts')->insertGetId([
                    'analysis_id'     => $analysisId,
                    'platform'        => $d['platform'] ?? 'Unknown',
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
