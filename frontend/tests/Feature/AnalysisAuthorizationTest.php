<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi otorisasi halaman analisis (menutup celah IDOR).
 *
 * Sebelum perbaikan, rute `analyses.show` dan `analyses.export` hanya dijaga
 * middleware `auth` — siapa pun yang login bisa membaca/mengunduh data analisis
 * milik pengguna lain dengan menebak ID pada URL.
 */
class AnalysisAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function makeAnalysis(User $owner): Analysis
    {
        return Analysis::create([
            'user_id'          => $owner->id,
            'title'            => 'Analisis Uji Otorisasi',
            'platform'         => 'x',
            'keywords'         => ['uji'],
            'search_mode'      => 'latest',
            'max_links'        => 10,
            'max_scroll_steps' => 10,
            'headless'         => true,
            'status'           => 'completed',
        ]);
    }

    public function test_analyst_dapat_melihat_analisis_miliknya_sendiri(): void
    {
        $analyst  = $this->userWithRole('analyst');
        $analysis = $this->makeAnalysis($analyst);

        $this->actingAs($analyst)
            ->getJson(route('analyses.show', $analysis))
            ->assertOk();
    }

    public function test_analyst_tidak_dapat_melihat_analisis_milik_orang_lain(): void
    {
        $owner    = $this->userWithRole('analyst');
        $penyusup = $this->userWithRole('analyst');
        $analysis = $this->makeAnalysis($owner);

        $this->actingAs($penyusup)
            ->getJson(route('analyses.show', $analysis))
            ->assertForbidden();
    }

    public function test_admin_dapat_melihat_analisis_milik_pengguna_lain(): void
    {
        $owner    = $this->userWithRole('analyst');
        $admin    = $this->userWithRole('admin');
        $analysis = $this->makeAnalysis($owner);

        $this->actingAs($admin)
            ->getJson(route('analyses.show', $analysis))
            ->assertOk();
    }

    public function test_analyst_dapat_mengekspor_analisis_miliknya_sendiri(): void
    {
        $analyst  = $this->userWithRole('analyst');
        $analysis = $this->makeAnalysis($analyst);

        $response = $this->actingAs($analyst)->get(route('analyses.export', $analysis));

        $response->assertOk();
        $this->assertStringContainsString(
            'text/csv',
            (string) $response->headers->get('Content-Type'),
            'Ekspor harus mengirim CSV.'
        );
    }

    public function test_analyst_tidak_dapat_mengekspor_analisis_milik_orang_lain(): void
    {
        $owner    = $this->userWithRole('analyst');
        $penyusup = $this->userWithRole('analyst');
        $analysis = $this->makeAnalysis($owner);

        $this->actingAs($penyusup)
            ->get(route('analyses.export', $analysis))
            ->assertForbidden();
    }

    public function test_pengguna_tanpa_permission_export_reports_tidak_dapat_mengekspor(): void
    {
        // Pemilik analisis, tetapi TIDAK punya role apa pun → tanpa permission export-reports.
        $owner    = User::factory()->create(['is_active' => true]);
        $analysis = $this->makeAnalysis($owner);

        $this->actingAs($owner)
            ->get(route('analyses.export', $analysis))
            ->assertForbidden();
    }

    public function test_halaman_detail_analisis_milik_sendiri_dapat_dirender(): void
    {
        $analyst  = $this->userWithRole('analyst');
        $analysis = $this->makeAnalysis($analyst);

        $this->actingAs($analyst)
            ->get(route('analyses.show', $analysis))
            ->assertOk();
    }
}
