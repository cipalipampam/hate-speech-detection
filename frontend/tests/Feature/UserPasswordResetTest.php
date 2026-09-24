<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresi reset kata sandi oleh admin pada menu Kelola Pengguna.
 *
 * Sebelumnya `UpdateUserRequest` menerima `password` tanpa aturan `confirmed` dan
 * form edit bahkan tidak memiliki field password — jadi reset password admin tidak
 * pernah bisa dipakai dari UI.
 */
class UserPasswordResetTest extends TestCase
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

    /**
     * @return array<string, string>
     */
    private function payload(User $target, string $password, ?string $confirmation = null): array
    {
        return [
            'name'                  => $target->name,
            'email'                 => $target->email,
            'role'                  => 'analyst',
            'is_active'             => '1',
            'password'              => $password,
            'password_confirmation' => $confirmation ?? $password,
        ];
    }

    public function test_admin_dapat_mereset_password_pengguna_dengan_konfirmasi(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('analyst');

        $response = $this->actingAs($this->admin())
            ->patch(route('admin.users.update', $target), $this->payload($target, 'passwordbaru123'));

        $response->assertRedirect();

        $this->assertTrue(
            Hash::check('passwordbaru123', $target->fresh()->password),
            'Password pengguna harus tergantikan setelah reset oleh admin.'
        );
    }

    public function test_reset_password_tanpa_konfirmasi_ditolak(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('analyst');
        $passwordLama = $target->password;

        $payload = $this->payload($target, 'passwordbaru123');
        unset($payload['password_confirmation']);

        $response = $this->actingAs($this->admin())
            ->patch(route('admin.users.update', $target), $payload);

        $response->assertSessionHasErrors('password');
        $this->assertSame($passwordLama, $target->fresh()->password, 'Password tidak boleh berubah.');
    }

    public function test_konfirmasi_password_tidak_cocok_ditolak(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('analyst');

        $response = $this->actingAs($this->admin())
            ->patch(route('admin.users.update', $target), $this->payload($target, 'passwordbaru123', 'beda12345'));

        $response->assertSessionHasErrors('password');
    }

    public function test_password_boleh_dikosongkan_saat_hanya_mengubah_profil(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('analyst');
        $passwordLama = $target->password;

        $response = $this->actingAs($this->admin())->patch(route('admin.users.update', $target), [
            'name'      => 'Nama Diubah',
            'email'     => $target->email,
            'role'      => 'analyst',
            'is_active' => '1',
            'password'  => '',
        ]);

        $response->assertRedirect();
        $this->assertSame('Nama Diubah', $target->fresh()->name);
        $this->assertSame($passwordLama, $target->fresh()->password, 'Password lama harus dipertahankan.');
    }
}
