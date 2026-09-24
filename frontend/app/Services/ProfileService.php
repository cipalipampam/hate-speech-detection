<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    /**
     * Memperbarui informasi nama dan email pengguna.
     *
     * @param  User  $user
     * @param  array{name: string, email: string}  $data
     * @return bool
     */
    public function updateProfileInfo(User $user, array $data): bool
    {
        return $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);
    }

    /**
     * Memperbarui kata sandi pengguna dengan hashing bcrypt aman.
     *
     * @param  User  $user
     * @param  string  $newPassword
     * @return bool
     */
    public function updatePassword(User $user, string $newPassword): bool
    {
        return $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }
}
