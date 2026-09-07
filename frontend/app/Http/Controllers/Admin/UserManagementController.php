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
        $query = User::withCount('analyses')->with('roles');

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
        }

        if ($request->filled('role') && strtolower($request->query('role')) !== 'all') {
            $role = strtolower(trim($request->query('role')));
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($request->filled('status') && strtolower($request->query('status')) !== 'all') {
            $statusVal = strtolower(trim($request->query('status')));
            if (in_array($statusVal, ['active', 'aktif', '1'], true)) {
                $query->where('is_active', 1);
            } elseif (in_array($statusVal, ['inactive', 'nonaktif', '0'], true)) {
                $query->where('is_active', 0);
            }
        }

        $users = $query->latest()->paginate(15)->withQueryString();

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
