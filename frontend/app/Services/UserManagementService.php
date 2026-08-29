<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserManagementService
{
    /**
     * Mengambil daftar pengguna beserta Role Spatie dengan fitur pencarian.
     */
    public function getPaginatedUsers(int $perPage = 10, ?string $search = null)
    {
        $query = User::with('roles')->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
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
     * Menghapus akun pengguna (beserta cascading analyses).
     */
    public function deleteUser(User $user): bool
    {
        return $user->delete();
    }
}
