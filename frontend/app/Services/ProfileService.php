<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    /**
     * Perbarui nama dan email pengguna.
     */
    public function updateProfileInfo(User $user, array $data): bool
    {
        return $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);
    }

    /**
     * Perbarui kata sandi pengguna (di-hash bcrypt).
     */
    public function updatePassword(User $user, string $newPassword): bool
    {
        return $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }
}
