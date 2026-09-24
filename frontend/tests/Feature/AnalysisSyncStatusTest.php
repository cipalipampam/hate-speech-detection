<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\User;
use App\Services\AnalysisImportService;
use App\Services\AnalysisService;
use App\Services\FastAPIClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Regresi `AnalysisService::syncAnalysisStatus()`.
 *
 * Method ini menyuntikkan dua atribut VIRTUAL (`pipeline_message`, `pipeline_step`)
 * yang bukan kolom tabel `analyses`. Sebelum diperbaiki, atribut itu membuat model
 * dianggap "kotor" sehingga `save()` akan gagal dengan error
 * "Unknown column 'pipeline_message'".
 */
class AnalysisSyncStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeRunningAnalysis(): Analysis
    {
        return Analysis::create([
            'user_id'          => User::factory()->create()->id,
            'job_id'           => 'job-uji-123',
            'title'            => 'Analisis Uji Sync',
            'platform'         => 'x',
            'keywords'         => ['uji'],
            'search_mode'      => 'latest',
            'max_links'        => 10,
            'max_scroll_steps' => 10,
            'headless'         => true,
            'status'           => 'running',
        ]);
    }

    private function serviceReturningJobStatus(array $jobData): AnalysisService
    {
        $fakeClient = Mockery::mock(FastAPIClientService::class);
        $fakeClient->shouldReceive('getPipelineStatus')->andReturn([
            'success' => true,
            'data'    => $jobData,
        ]);

        return new AnalysisService($fakeClient, new AnalysisImportService($fakeClient));
    }

    public function test_sync_status_mengisi_atribut_virtual_untuk_ui(): void
    {
        $analysis = $this->makeRunningAnalysis();

        $synced = $this->serviceReturningJobStatus([
            'status'  => 'running',
            'message' => 'Polling job asinkron sedang berjalan...',
        ])->syncAnalysisStatus($analysis);

        // Pesan teknis polling difilter agar ramah pengguna.
        $this->assertSame('Pipeline analisis sedang diproses oleh sistem...', $synced->pipeline_message);
        $this->assertSame(1, $synced->pipeline_step);
    }

    public function test_atribut_virtual_tidak_membuat_model_kotor_sehingga_aman_disimpan(): void
    {
        $analysis = $this->makeRunningAnalysis();

        $synced = $this->serviceReturningJobStatus([
            'status'  => 'running',
            'message' => 'Polling job asinkron sedang berjalan...',
        ])->syncAnalysisStatus($analysis);

        $this->assertFalse(
            $synced->isDirty(),
            'Atribut virtual (pipeline_message/pipeline_step) tidak boleh membuat model kotor.'
        );

        // Tanpa perbaikan, baris ini melempar QueryException: Unknown column 'pipeline_message'.
        $synced->save();

        $this->assertDatabaseHas('analyses', [
            'id'     => $analysis->id,
            'status' => 'running',
        ]);
    }
}
