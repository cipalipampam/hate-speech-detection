<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\FilterUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        protected UserManagementService $userService
    ) {}

    public function index(FilterUserRequest $request): View
    {
        $users   = $this->userService->getFilteredUsers($request->validated());
        $metrics = $this->userService->getUserMetrics();

        return view('admin.users.index', [
            'users'       => $users,
            'roleCounts'  => $metrics['roleCounts'],
            'activeCount' => $metrics['activeCount'],
        ]);
    }

    public function create(): View
    {
        // Query role dititipkan ke service layer (thin controller).
        return view('admin.users.create', ['roles' => $this->userService->getAssignableRoles()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->userService->createUser($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna "' . $user->name . '" berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user'  => $user,
            'roles' => $this->userService->getAssignableRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->updateUser($user, $request->validated());

        return back()->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        // Jangan nonaktifkan diri sendiri
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        $isActive   = $this->userService->toggleActiveStatus($user);
        $statusText = $isActive ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', 'Akun "' . $user->name . '" berhasil ' . $statusText . '.');
    }

    /**
     * Hapus akun pengguna (ditolak bila akun masih memiliki sesi analisis).
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $error = $this->userService->deleteUser($user);

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Akun "' . $user->name . '" berhasil dihapus.');
    }
}
