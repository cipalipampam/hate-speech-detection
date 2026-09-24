<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FastAPIClientService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Regresi halaman telemetri scraper setelah penyederhanaan UI.
 *
 * Panel panduan login (`scraper/partials/login-guide.blade.php`) dihapus atas
 * permintaan pemilik proyek. Yang WAJIB tetap ada:
 *   - tombol RE-AUTHENTICATE (dipicu `requestLogin()` untuk membuka noVNC di Docker),
 *   - `loginEnvironment` pada payload telemetri (sumber `guiMode`/`novncUrl`),
 *   - flash `success` dari triggerLogin tanpa flash panduan `login_triggered_platform`.
 */
class ScraperStatusPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $sessionData
     */
    private function fakeTelemetry(string $guiMode, array $sessionData = []): void
    {
        $client = Mockery::mock(FastAPIClientService::class);
        $client->shouldReceive('getTelemetryData')->andReturn([
            'isServerOnline'   => true,
            'sessionData'      => $sessionData + [
                'x'       => ['is_valid' => false, 'message' => 'Cookie X kadaluarsa.'],
                'threads' => ['is_valid' => true, 'message' => 'Sesi Threads aktif.'],
            ],
            'loginEnvironment' => [
                'runtime'    => $guiMode === 'novnc' ? 'docker' : 'local',
                'gui_mode'   => $guiMode,
                'novnc_url'  => 'http://localhost:6080/vnc.html',
                'message'    => 'Pesan lingkungan GUI dari server AI.',
            ],
            'statusResult'     => ['success' => true, 'data' => []],
            'serviceChannel'   => 'Internal Microservice Bridge',
        ]);

        $this->app->instance(FastAPIClientService::class, $client);
    }

    public function test_halaman_telemetri_render_tanpa_panel_panduan_login(): void
    {
        $this->fakeTelemetry('native');

        $response = $this->actingAs($this->admin())->get(route('scraper.status'));

        $response->assertOk();
        $response->assertSee('RE-AUTHENTICATE');

        // Teks panel panduan yang sudah dihapus tidak boleh muncul kembali.
        $response->assertDontSee('BROWSER GUI DIBUKA DI PERANGKAT INI');
        $response->assertDontSee('BROWSER GUI AKTIF VIA NOVNC');
        $response->assertDontSee('LOGIN INTERAKTIF TIDAK TERSEDIA');
    }

    public function test_payload_telemetri_masih_membawa_login_environment(): void
    {
        $this->fakeTelemetry('novnc');

        $response = $this->actingAs($this->admin())
            ->getJson(route('scraper.status'));

        $response->assertOk()
            ->assertJsonPath('loginEnvironment.gui_mode', 'novnc')
            ->assertJsonPath('loginEnvironment.novnc_url', 'http://localhost:6080/vnc.html');
    }

    public function test_trigger_login_tidak_lagi_menyimpan_flash_panduan(): void
    {
        $client = Mockery::mock(FastAPIClientService::class);
        $client->shouldReceive('triggerLogin')
            ->once()
            ->with('x')
            ->andReturn(['success' => true, 'data' => ['message' => 'Jendela login dibuka.']]);

        $this->app->instance(FastAPIClientService::class, $client);

        $response = $this->actingAs($this->admin())
            ->post(route('scraper.login-trigger', 'x'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionMissing('login_triggered_platform');
    }
}
