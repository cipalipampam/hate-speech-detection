<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserManagementService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresi penghapusan role `viewer` (keputusan 2026-09-24).
 *
 * Sistem hanya mengenal dua role: `admin` dan `analyst`. Test ini menjaga agar
 * role viewer tidak muncul kembali lewat seeder, validasi Form Request, maupun UI.
 */
class UserRoleManagementTest extends TestCase
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

    public function test_seeder_hanya_membuat_role_admin_dan_analyst(): void
    {
        $this->assertSame(
            ['admin', 'analyst'],
            Role::orderBy('name')->pluck('name')->all(),
            'Seeder tidak boleh lagi membuat role viewer.'
        );
    }

    public function test_seeder_tidak_membuat_akun_demo_viewer(): void
    {
        $this->assertDatabaseMissing('users', ['email' => 'viewer@hatespeech.test']);
        $this->assertDatabaseHas('users', ['email' => 'admin@hatespeech.test']);
        $this->assertDatabaseHas('users', ['email' => 'analyst@hatespeech.test']);
    }

    public function test_admin_tidak_dapat_membuat_pengguna_dengan_role_viewer(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name'                  => 'Uji Viewer',
            'email'                 => 'uji.viewer@hatespeech.test',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'viewer',
            'is_active'             => '1',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'uji.viewer@hatespeech.test']);
    }

    public function test_admin_dapat_membuat_pengguna_analyst(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name'                  => 'Analis Baru',
            'email'                 => 'analis.baru@hatespeech.test',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'analyst',
            'is_active'             => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'analis.baru@hatespeech.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('analyst'));
    }

    public function test_admin_tidak_dapat_mengubah_role_pengguna_menjadi_viewer(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('analyst');

        $response = $this->actingAs($this->admin())->patch(route('admin.users.update', $target), [
            'name'      => $target->name,
            'email'     => $target->email,
            'role'      => 'viewer',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertTrue($target->fresh()->hasRole('analyst'));
    }

    public function test_metrik_role_tidak_lagi_memuat_viewer(): void
    {
        $metrics = app(UserManagementService::class)->getUserMetrics();

        $this->assertSame(['admin', 'analyst'], array_keys($metrics['roleCounts']));
        $this->assertArrayNotHasKey('viewer', $metrics['roleCounts']);
    }

    public function test_halaman_kelola_pengguna_tidak_menampilkan_opsi_viewer(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertDontSee('VIEWER', false);
        $response->assertDontSee('viewer@hatespeech.test', false);
    }
}
