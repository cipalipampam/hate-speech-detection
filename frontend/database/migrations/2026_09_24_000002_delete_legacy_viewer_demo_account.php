<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Menghapus akun demo lama `viewer@hatespeech.test`.
     *
     * Akun ini tidak lagi dibuat oleh `RoleAndPermissionSeeder` (role `viewer` sudah
     * dihapus pada 2026-09-24), tetapi masih tersisa di database yang pernah di-seed
     * oleh versi seeder lama.
     *
     * PENGAMAN: akun TIDAK dihapus bila masih memiliki sesi analisis, karena relasi
     * `analyses.user_id` memakai ON DELETE CASCADE — penghapusan akun akan ikut
     * menghapus data riset. Kasus tersebut dicatat ke log agar bisa ditangani manual.
     */
    public function up(): void
    {
        $user = User::where('email', 'viewer@hatespeech.test')->first();

        if (! $user) {
            return;
        }

        $analysisCount = $user->analyses()->count();

        if ($analysisCount > 0) {
            Log::warning(
                "Akun demo legacy {$user->email} TIDAK dihapus: masih memiliki "
                . "{$analysisCount} sesi analisis. Pindahkan atau hapus analisisnya lebih dulu."
            );

            return;
        }

        // Bersihkan baris pivot Spatie agar tidak menyisakan model_has_roles yatim.
        $user->syncRoles([]);
        $user->delete();
    }

    /**
     * Tidak reversibel: akun demo hanya alat bantu pengujian dan tidak memuat data.
     * Bila diperlukan lagi, cukup buat ulang lewat menu Kelola Pengguna.
     */
    public function down(): void
    {
        //
    }
};
