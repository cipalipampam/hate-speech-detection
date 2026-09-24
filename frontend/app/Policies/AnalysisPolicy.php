<?php

namespace App\Policies;

use App\Models\Analysis;
use App\Models\User;

/**
 * Otorisasi akses Analysis (menutup celah IDOR): admin bebas, selain admin hanya miliknya sendiri.
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
