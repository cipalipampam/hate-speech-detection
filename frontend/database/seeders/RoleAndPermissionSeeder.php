<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cache permission Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Buat Permissions
        $permissions = [
            'manage-users',
            'manage-auth-sessions',
            'run-analysis',
            'test-single-prediction',
            'view-dashboard',
            'export-reports',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // 2. Buat Roles & Assign Permissions
        // Role Admin: memiliki semua permission
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        // Role Analyst: riset, scraping, inferensi, export
        $analystRole = Role::firstOrCreate(['name' => 'analyst', 'guard_name' => 'web']);
        $analystRole->syncPermissions([
            'manage-auth-sessions',
            'run-analysis',
            'test-single-prediction',
            'view-dashboard',
            'export-reports',
        ]);

        // CATATAN: role `viewer` sudah DIHAPUS (keputusan 2026-09-24) — sistem hanya
        // mengenal `admin` dan `analyst`. Akun lama ber-role viewer dipindahkan ke
        // analyst oleh migrasi 2026_09_24_000001_reassign_viewer_role_to_analyst.

        // 3. Buat Akun Demo Default
        // Akun Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@hatespeech.test'],
            [
                'name' => 'Administrator Sistem',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $admin->syncRoles([$adminRole]);

        // Akun Analyst (Peneliti)
        $analyst = User::firstOrCreate(
            ['email' => 'analyst@hatespeech.test'],
            [
                'name' => 'Peneliti Data Sentimen',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $analyst->syncRoles([$analystRole]);
    }
}
