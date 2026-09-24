<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\User;
use App\Services\AnalysisImportService;
use App\Services\FastAPIClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Regresi untuk AnalysisImportService — jalur impor CSV hasil pipeline FastAPI.
 *
 * Konteks bug: ekspor pipeline backend ditulis dengan `encoding="utf-8-sig"`
 * (backend/src/pipeline/end_to_end_pipeline.py) sehingga file SELALU diawali BOM.
 * Karena nama kolom pertama adalah `platform`, BOM membuat key hasil array_combine
 * menjadi "\xEF\xBB\xBFplatform" → lookup $d['platform'] gagal dan platform post
 * hanya ditebak dari source_url.
 */
class AnalysisImportServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Header CSV persis seperti yang dihasilkan pipeline backend.
     *
     * @var array<int, string>
     */
    private const HEADER = [
        'platform', 'source', 'user_id', 'type', 'date', 'content',
        'label_lvl1', 'label_lvl2', 'confidence_lvl1', 'confidence_lvl2',
    ];

    /**
     * Membangun CSV seperti keluaran backend (opsional dengan BOM UTF-8).
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function buildCsv(array $rows, bool $withBom = true): string
    {
        $lines = [implode(',', self::HEADER)];

        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        return ($withBom ? "\xEF\xBB\xBF" : '') . implode("\n", $lines) . "\n";
    }

    /**
     * Menyiapkan service dengan FastAPIClient yang di-stub.
     * Nama file sengaja unik agar jalur fallback filesystem lokal TIDAK terpakai
     * (di test, file lokal tidak ada) sehingga yang diuji adalah jalur HTTP/Docker.
     */
    private function serviceReturningCsv(string $csv): AnalysisImportService
    {
        $fakeClient = Mockery::mock(FastAPIClientService::class);
        $fakeClient->shouldReceive('downloadExportToStream')
            ->andReturnUsing(function (string $filename, $sink) use ($csv): bool {
                fwrite($sink, $csv);
                return true;
            });

        return new AnalysisImportService($fakeClient);
    }

    private function createAnalysis(string $platform = 'both'): Analysis
    {
        return Analysis::create([
            'user_id'          => User::factory()->create()->id,
            'title'            => 'Uji Impor BOM',
            'platform'         => $platform,
            'keywords'         => ['uji'],
            'search_mode'      => 'latest',
            'max_links'        => 10,
            'max_scroll_steps' => 10,
            'headless'         => true,
            'status'           => 'completed',
        ]);
    }

    public function test_import_membaca_kolom_platform_saat_csv_punya_bom_utf8(): void
    {
        // Baris ini adalah kasus yang paling mudah salah:
        // kolom platform CSV = X, tetapi source_url adalah shortlink t.co sehingga
        // heuristik URL TIDAK bisa menentukan platform. Satu-satunya cara benar
        // adalah membaca kolom `platform` dari CSV.
        $csv = $this->buildCsv([
            ['X,https://t.co/abc123,akunuji,Original Post,2026-09-17T13:08:22.000Z,teks uji satu,non_hate_speech,tidak_relevan,0.9996,0.9996'],
            ['Threads,https://t.co/def456,akunlain,Reply/Comment,2026-09-18T10:00:00.000Z,teks uji dua,hate_speech,dehumanisasi,0.9871,0.9700'],
        ]);

        $service  = $this->serviceReturningCsv($csv);
        $analysis = $this->createAnalysis('both');

        $imported = $service->importPostsFromCsv($analysis, '__test_bom_import.csv');

        $this->assertSame(2, $imported, 'Dua baris CSV harus terimpor.');

        $platforms = $analysis->posts()->orderBy('id')->pluck('platform')->all();

        $this->assertSame(
            ['X', 'Threads'],
            $platforms,
            'Kolom `platform` dari CSV harus dipakai apa adanya, bukan hasil tebakan source_url.'
        );
    }

    public function test_import_tetap_benar_untuk_csv_tanpa_bom(): void
    {
        $csv = $this->buildCsv([
            ['X,https://t.co/abc123,akunuji,Original Post,2026-09-17T13:08:22.000Z,teks uji,non_hate_speech,tidak_relevan,0.9996,0.9996'],
        ], withBom: false);

        $service  = $this->serviceReturningCsv($csv);
        $analysis = $this->createAnalysis('both');

        $this->assertSame(1, $service->importPostsFromCsv($analysis, '__test_nobom_import.csv'));

        $this->assertSame('X', $analysis->posts()->first()->platform);
    }

    public function test_import_memetakan_kolom_lainnya_dengan_benar(): void
    {
        $csv = $this->buildCsv([
            ['Threads,https://www.threads.com/@akunuji/post/xyz,akunuji,Reply/Comment,2026-09-18T10:00:00.000Z,konten uji,hate_speech,dehumanisasi,0.9871,0.9700'],
        ]);

        $service  = $this->serviceReturningCsv($csv);
        $analysis = $this->createAnalysis('both');

        $service->importPostsFromCsv($analysis, '__test_mapping_import.csv');

        $post = $analysis->posts()->with('classification')->first();

        $this->assertSame('Threads', $post->platform);
        $this->assertSame('akunuji', $post->author_username);
        $this->assertSame('Reply/Comment', $post->post_type);
        $this->assertSame('https://www.threads.com/@akunuji/post/xyz', $post->source_url);

        $this->assertSame('konten uji', $post->classification->raw_content);
        $this->assertSame('hate_speech', $post->classification->label_lvl1);
        $this->assertSame('dehumanisasi', $post->classification->label_lvl2);
    }

    /**
     * Kontrak ekspor→impor (setelah perbaikan A3 di backend):
     * CSV pipeline memuat DUA kolom teks — 'content' (mentah) dan 'clean_text' (bersih).
     * Frontend harus memetakan mentah → raw_content dan bersih → clean_content.
     */
    public function test_import_memisahkan_teks_mentah_dan_teks_bersih(): void
    {
        $header = array_merge(self::HEADER, ['clean_text']);
        $row = [
            'X',
            'https://x.com/akun/status/1',
            'akunuji',
            'Original Post',
            '2026-09-17T13:08:22.000Z',
            'Lihat https://x.com/akun/status/1 KAMU semua bodoh',
            'hate_speech',
            'dehumanisasi',
            '0.99',
            '0.98',
            'lihat akun kamu semua bodoh',
        ];

        $csv = "\xEF\xBB\xBF" . implode(',', $header) . "\n" . implode(',', $row) . "\n";

        $service  = $this->serviceReturningCsv($csv);
        $analysis = $this->createAnalysis('both');

        $this->assertSame(1, $service->importPostsFromCsv($analysis, '__test_rawclean_import.csv'));

        $classification = $analysis->posts()->with('classification')->first()->classification;

        $this->assertSame(
            'Lihat https://x.com/akun/status/1 KAMU semua bodoh',
            $classification->raw_content,
            'Teks mentah harus tersimpan utuh di raw_content.'
        );
        $this->assertSame(
            'lihat akun kamu semua bodoh',
            $classification->clean_content,
            'Teks bersih harus tersimpan di clean_content.'
        );
    }

    public function test_import_tidak_menjalankan_query_n_plus_1_ke_tabel_analyses(): void
    {
        $csv = $this->buildCsv([
            ['X,https://t.co/aaa111,akun1,Original Post,2026-09-17T13:08:22.000Z,teks satu,non_hate_speech,tidak_relevan,0.9,0.9'],
            ['Threads,https://t.co/bbb222,akun2,Original Post,2026-09-17T13:08:22.000Z,teks dua,non_hate_speech,tidak_relevan,0.9,0.9'],
            ['X,https://t.co/ccc333,akun3,Original Post,2026-09-17T13:08:22.000Z,teks tiga,non_hate_speech,tidak_relevan,0.9,0.9'],
        ]);

        $service  = $this->serviceReturningCsv($csv);
        $analysis = $this->createAnalysis('both');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $service->importPostsFromCsv($analysis, '__test_nplus1_import.csv');

        // Hitung hanya SELECT ke tabel `analyses` (bukan insert ke analysis_posts).
        $parentPlatformQueries = collect(DB::getQueryLog())
            ->filter(fn (array $log) => str_contains($log['query'], 'from "analyses"')
                || str_contains($log['query'], 'from `analyses`'))
            ->count();

        DB::disableQueryLog();

        $this->assertSame(3, $analysis->posts()->count());
        $this->assertLessThanOrEqual(
            1,
            $parentPlatformQueries,
            'Query platform induk harus dijalankan sekali per batch, bukan sekali per baris (N+1).'
        );
    }

    public function test_import_mengembalikan_nol_bila_unduhan_gagal(): void
    {
        $fakeClient = Mockery::mock(FastAPIClientService::class);
        $fakeClient->shouldReceive('downloadExportToStream')->andReturnFalse();

        $service  = new AnalysisImportService($fakeClient);
        $analysis = $this->createAnalysis('both');

        $this->assertSame(0, $service->importPostsFromCsv($analysis, '__gagal_unduh.csv'));
        $this->assertSame(0, $analysis->posts()->count());
    }

    /**
     * Impor HARUS selalu lewat HTTP FastAPI, walau file dengan nama sama kebetulan ada
     * di filesystem backend (jalur lokal sudah dihapus — prinsip anti-path-mismatch).
     */    public function test_import_selalu_lewat_http_walau_file_lokal_bernama_sama_ada(): void
    {
        $decoyName = '__decoy_http_only.csv';
        $decoyPath = base_path('../backend/storage/exports/' . $decoyName);

        // File umpan (decoy) berisi data berbeda — tidak boleh dipakai lagi.
        file_put_contents($decoyPath, $this->buildCsv([
            ['Threads,https://t.co/decoy,akun_decoy,Original Post,2026-09-17T13:08:22.000Z,teks decoy,non_hate_speech,tidak_relevan,0.9,0.9'],
        ]));

        try {
            $csv = $this->buildCsv([
                ['X,https://t.co/http123,akun_http,Original Post,2026-09-17T13:08:22.000Z,teks http,non_hate_speech,tidak_relevan,0.9,0.9'],
            ]);

            $service  = $this->serviceReturningCsv($csv);
            $analysis = $this->createAnalysis('both');

            $this->assertSame(1, $service->importPostsFromCsv($analysis, $decoyName));

            $post = $analysis->posts()->first();

            $this->assertSame(
                'akun_http',
                $post->author_username,
                'Data harus berasal dari HTTP FastAPI, bukan dari file lokal backend.'
            );
            $this->assertSame('X', $post->platform);
        } finally {
            if (file_exists($decoyPath)) {
                unlink($decoyPath);
            }
        }
    }
}
