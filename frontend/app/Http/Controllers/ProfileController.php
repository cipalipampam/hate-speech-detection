<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileService $profileService
    ) {}

    public function edit(): View
    {
        return view('profile.edit');
    }

    /**
     * Perbarui nama dan email pengguna.
     */
    public function updateInfo(UpdateProfileRequest $request): RedirectResponse
    {
        $this->profileService->updateProfileInfo($request->user(), $request->validated());

        return back()->with('profile_success', 'Informasi akun berhasil diperbarui.');
    }

    /**
     * Perbarui kata sandi pengguna.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->profileService->updatePassword($request->user(), $request->validated('password'));

        return back()->with('profile_success', 'Kata sandi berhasil diperbarui.');
    }
}
