<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Role `viewer` dihapus dari sistem — hanya menyisakan `admin` dan `analyst`
     * (keputusan pemilik proyek, 2026-09-24).
     *
     * Akun yang masih memegang role `viewer` dipindahkan ke role `analyst` supaya
     * tidak ada pengguna yang terlanjur kehilangan seluruh hak akses (semua halaman 403).
     *
     * Catatan: pada instalasi baru migrasi ini tidak melakukan apa-apa karena role
     * `viewer` tidak pernah dibuat oleh `RoleAndPermissionSeeder`.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $viewerRole = Role::where('name', 'viewer')->where('guard_name', 'web')->first();

        if (! $viewerRole) {
            return;
        }

        $analystRole = Role::firstOrCreate(['name' => 'analyst', 'guard_name' => 'web']);

        foreach (User::role('viewer')->get() as $user) {
            $user->syncRoles([$analystRole]);
        }

        // Menghapus role sekaligus membersihkan baris pivot model_has_roles (perilaku Spatie).
        $viewerRole->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reversibel sebagian: role `viewer` beserta permission lamanya dibuat ulang.
     * Pemetaan pengguna tidak dikembalikan (tidak disimpan) — akun tetap `analyst`
     * dan dapat dipindahkan manual lewat menu Kelola Pengguna.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewerRole->syncPermissions(['view-dashboard', 'export-reports']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
