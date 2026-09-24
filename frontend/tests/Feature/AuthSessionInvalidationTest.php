<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi keamanan sesi: setelah password diganti, sesi lain (perangkat/peramban lain)
 * harus ikut logout. Dijamin oleh middleware `AuthenticateSession` yang membandingkan
 * hash password di session dengan password user saat ini.
 */
class AuthSessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function analyst(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('analyst');

        return $user;
    }

    public function test_sesi_dengan_hash_password_lama_dipaksa_logout(): void
    {
        $user    = $this->analyst();
        $hashLama = $user->getAuthPassword();

        // Ganti password lewat halaman Profil.
        $this->actingAs($user)->patch(route('profile.password'), [
            'current_password'      => 'password',
            'password'              => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])->assertRedirect();

        // Simulasi sesi di perangkat lain yang masih menyimpan hash password LAMA.
        $this->withSession(['password_hash_web' => $hashLama])
            ->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_sesi_dengan_hash_password_terbaru_tetap_valid(): void
    {
        $user = $this->analyst();

        $this->withSession(['password_hash_web' => $user->getAuthPassword()])
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_sesi_baru_tanpa_hash_tidak_terlogout(): void
    {
        // Sesi lama (dibuat sebelum middleware aktif) belum menyimpan hash apa pun —
        // harus tetap bisa dipakai dan hash-nya baru disimpan setelah request ini.
        $user = $this->analyst();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
