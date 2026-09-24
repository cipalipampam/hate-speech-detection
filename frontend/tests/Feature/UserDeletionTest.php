<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi fitur hapus akun pengguna (menu Kelola Pengguna).
 *
 * Aturan penting: akun yang masih memiliki sesi analisis TIDAK boleh terhapus,
 * karena relasi analyses.user_id memakai ON DELETE CASCADE (data riset akan hilang).
 */
class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function analyst(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('analyst');

        return $user;
    }

    public function test_admin_dapat_menghapus_pengguna_yang_tidak_punya_analisis(): void
    {
        $target = $this->analyst();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $target));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $target->id]);
    }

    public function test_admin_tidak_dapat_menghapus_akun_sendiri(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_akun_yang_masih_memiliki_analisis_tidak_dapat_dihapus(): void
    {
        $target = $this->analyst();

        Analysis::create([
            'user_id'          => $target->id,
            'title'            => 'Analisis Milik Target',
            'platform'         => 'x',
            'keywords'         => ['uji'],
            'search_mode'      => 'latest',
            'max_links'        => 10,
            'max_scroll_steps' => 10,
            'headless'         => true,
            'status'           => 'completed',
        ]);

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $target));

        $response->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('analyses', ['user_id' => $target->id]);
    }

    public function test_analyst_tidak_dapat_menghapus_pengguna(): void
    {
        $analyst = $this->analyst();
        $target  = $this->analyst();

        $this->actingAs($analyst)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
