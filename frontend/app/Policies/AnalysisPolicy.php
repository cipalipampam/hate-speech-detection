<?php

namespace App\Policies;

use App\Models\Analysis;
use App\Models\User;

/**
 * Policy: Analysis
 *
 * Menutup celah IDOR: halaman detail & unduhan CSV sebelumnya hanya dijaga
 * middleware `auth`, sehingga siapa pun yang login bisa membaca data analisis
 * milik pengguna lain hanya dengan menebak ID pada URL.
 *
 * Aturan:
 *   - admin   → boleh mengakses semua sesi analisis.
 *   - lainnya → hanya sesi analisis miliknya sendiri.
 *   - export  → sama seperti view, TETAPI wajib punya permission `export-reports`.
 *
 * Policy ini ditemukan otomatis oleh Laravel (App\Policies\AnalysisPolicy → App\Models\Analysis).
 */
class AnalysisPolicy
{
    /**
     * Melihat detail satu sesi analisis (halaman HTML maupun endpoint JSON/AJAX).
     */
    public function view(User $user, Analysis $analysis): bool
    {
        return $user->hasRole('admin') || (int) $analysis->user_id === (int) $user->id;
    }

    /**
     * Mengunduh hasil analisis sebagai CSV.
     */
    public function export(User $user, Analysis $analysis): bool
    {
        return $user->can('export-reports') && $this->view($user, $analysis);
    }
}
