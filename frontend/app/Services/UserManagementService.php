<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserManagementService
{
    /**
     * Daftar pengguna terpaginasi dengan filter pencarian/role/status (data sudah tervalidasi).
     */
    public function getFilteredUsers(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::withCount('analyses')->with('roles');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $role = strtolower(trim((string) ($filters['role'] ?? 'all')));
        if ($role !== '' && $role !== 'all') {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        $statusVal = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if (in_array($statusVal, ['active', 'aktif', '1'], true)) {
            $query->where('is_active', 1);
        } elseif (in_array($statusVal, ['inactive', 'nonaktif', '0'], true)) {
            $query->where('is_active', 0);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * Hitung jumlah pengguna per role dan total pengguna aktif.
     */
    public function getUserMetrics(): array
    {
        // Satu query agregat untuk semua role (sebelumnya 3 query count terpisah).
        $roleCounts = Role::withCount('users')->pluck('users_count', 'name');

        return [
            'roleCounts' => [
                'admin'   => (int) ($roleCounts['admin'] ?? 0),
                'analyst' => (int) ($roleCounts['analyst'] ?? 0),
            ],
            'activeCount' => User::where('is_active', true)->count(),
        ];
    }

    /**
     * Role yang boleh dipilih admin pada form pengguna (hanya admin & analyst).
     */
    public function getAssignableRoles()
    {
        return Role::whereIn('name', ['admin', 'analyst'])->orderBy('name')->get();
    }

    /**
     * Membuat akun pengguna baru dan menetapkan Role Spatie.
     */
    public function createUser(array $data): User
    {
        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (!empty($data['role'])) {
            $role = Role::findOrCreate($data['role'], 'web');
            $user->syncRoles([$role]);
        }

        return $user;
    }

    /**
     * Memperbarui informasi akun pengguna dan Role Spatie.
     */
    public function updateUser(User $user, array $data): User
    {
        $updateData = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $user->is_active,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        if (!empty($data['role'])) {
            $role = Role::findOrCreate($data['role'], 'web');
            $user->syncRoles([$role]);
        }

        return $user->fresh('roles');
    }

    /**
     * Mengubah status aktif/non-aktif akun pengguna.
     */
    public function toggleActiveStatus(User $user): bool
    {
        $user->is_active = !$user->is_active;
        $user->save();

        return $user->is_active;
    }

    /**
     * Hapus akun; tolak bila masih punya sesi analisis (CASCADE akan menghapus data riset).
     *
     * Kembalikan pesan penolakan, atau null bila akun berhasil dihapus.
     */
    public function deleteUser(User $user): ?string
    {
        $analysisCount = $user->analyses()->count();

        if ($analysisCount > 0) {
            return 'Akun "' . $user->name . '" masih memiliki ' . $analysisCount
                . ' sesi analisis. Nonaktifkan akun ini saja agar data riset tidak ikut terhapus.';
        }

        // Bersihkan baris pivot Spatie agar tidak menyisakan model_has_roles yatim.
        $user->syncRoles([]);
        $user->delete();

        return null;
    }
}
