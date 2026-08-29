<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Urutan penting: roles/permissions/users dulu, baru data demo.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class, // Users, roles, permissions
            DemoAnalysisSeeder::class,      // Data analisis demo untuk presentasi
        ]);
    }
}

