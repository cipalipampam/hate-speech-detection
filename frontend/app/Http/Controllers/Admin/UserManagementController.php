<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function __construct(
        protected UserManagementService $userService
    ) {}

    public function index(Request $request): View
    {
        $users = User::withCount('analyses')
            ->with('roles')
            ->latest()
            ->paginate(15);

        $roleCounts = [
            'admin'   => User::role('admin')->count(),
            'analyst' => User::role('analyst')->count(),
            'viewer'  => User::role('viewer')->count(),
        ];
        $activeCount = User::where('is_active', true)->count();

        return view('admin.users.index', compact('users', 'roleCounts', 'activeCount'));
    }

    public function create(): View
    {
        $roles = Role::all();
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = $this->userService->createUser($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna "' . $user->name . '" berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        $roles = Role::all();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'role'  => ['required', 'string', 'exists:roles,name'],
        ]);

        $this->userService->updateUser($user, $data);

        return back()->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        // Jangan nonaktifkan diri sendiri
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        $user->update(['is_active' => !$user->is_active]);
        $statusText = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', 'Akun "' . $user->name . '" berhasil ' . $statusText . '.');
    }
}
