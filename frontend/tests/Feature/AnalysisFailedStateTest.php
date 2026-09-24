<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi tampilan analisis berstatus GAGAL.
 *
 * Banner besar `ERROR: PIPELINE GAGAL` di atas kartu pipeline dihapus atas
 * permintaan pemilik proyek. Status gagal kini disajikan DI DALAM kartu pipeline
 * (judul + badge + baris pesan berisi `error_message`), sehingga alasan kegagalan
 * tetap terbaca tanpa banner.
 */
class AnalysisFailedStateTest extends TestCase
{
    use RefreshDatabase;

    private const ERROR_MESSAGE = 'Job analisis tidak ditemukan di server AI (kemungkinan server direstart). Silakan buat analisis baru.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function failedAnalysis(User $owner): Analysis
    {
        return Analysis::create([
            'user_id'          => $owner->id,
            'title'            => 'Analisis Gagal Uji',
            'platform'         => 'x',
            'keywords'         => ['uji'],
            'search_mode'      => 'latest',
            'max_links'        => 10,
            'max_scroll_steps' => 10,
            'headless'         => true,
            'status'           => 'failed',
            'error_message'    => self::ERROR_MESSAGE,
        ]);
    }

    public function test_halaman_analisis_gagal_tanpa_banner_error(): void
    {
        $admin    = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $analysis = $this->failedAnalysis($admin);

        $response = $this->actingAs($admin)->get(route('analyses.show', $analysis));

        $response->assertOk();

        // Banner lama tidak boleh muncul lagi.
        $response->assertDontSee('ERROR: PIPELINE GAGAL');

        // Status gagal tetap terlihat: judul kartu, badge, dan alasan kegagalan.
        $response->assertSee('Pipeline AI Gagal Dieksekusi', false);
        $response->assertSee('GAGAL DIEKSEKUSI');
        $response->assertSee(self::ERROR_MESSAGE);
    }

    public function test_daftar_analisis_dan_payload_ajax_masih_membawa_error_message(): void
    {
        $admin    = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $analysis = $this->failedAnalysis($admin);

        // Dipakai cabang polling di browser saat status berubah running → failed.
        $this->actingAs($admin)
            ->getJson(route('analyses.show', $analysis))
            ->assertOk()
            ->assertJsonPath('analysis.status', 'failed')
            ->assertJsonPath('analysis.error_message', self::ERROR_MESSAGE);
    }
}
