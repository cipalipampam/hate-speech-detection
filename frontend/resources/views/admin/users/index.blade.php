@extends('layouts.app')

@section('title', 'Kelola Pengguna')

@section('breadcrumb')
<div style="display:flex;align-items:center;gap:0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    <span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">Kelola Pengguna</span>
</div>
@endsection

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <div>
        <h1 class="page-title">Kelola Pengguna</h1>
        <p class="page-subtitle">Manajemen akun, peran, dan status pengguna sistem.</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Tambah Pengguna
    </a>
</div>

{{-- Role Summary Cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
    @foreach([
        ['label'=>'Admin', 'role'=>'admin', 'count'=> $roleCounts['admin'] ?? 0, 'color'=>'var(--color-primary)', 'bg'=>'var(--color-primary-bg)'],
        ['label'=>'Analyst', 'role'=>'analyst', 'count'=> $roleCounts['analyst'] ?? 0, 'color'=>'var(--color-secondary-dark)', 'bg'=>'var(--color-secondary-bg)'],
        ['label'=>'Viewer', 'role'=>'viewer', 'count'=> $roleCounts['viewer'] ?? 0, 'color'=>'var(--color-teal)', 'bg'=>'var(--color-teal-bg)'],
        ['label'=>'Total Aktif', 'role'=>null, 'count'=> $activeCount ?? 0, 'color'=>'var(--color-navy)', 'bg'=>'#EEF3F8'],
    ] as $card)
    <div class="card" style="padding:1.125rem;">
        <p style="font-size:0.75rem;font-weight:700;color:var(--color-text-muted);margin-bottom:0.375rem;text-transform:uppercase;letter-spacing:0.05em;">{{ $card['label'] }}</p>
        <p style="font-size:1.75rem;font-weight:800;color:{{ $card['color'] }};">{{ $card['count'] }}</p>
    </div>
    @endforeach
</div>

{{-- Filter --}}
<div class="card" style="padding:0.875rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div style="flex:1;min-width:200px;position:relative;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" placeholder="Cari nama atau email..." class="input" style="padding-left:2.25rem;font-size:0.875rem;">
    </div>
    <select class="input" style="width:auto;font-size:0.875rem;cursor:pointer;">
        <option>Semua Peran</option>
        <option>Admin</option>
        <option>Analyst</option>
        <option>Viewer</option>
    </select>
    <select class="input" style="width:auto;font-size:0.875rem;cursor:pointer;">
        <option>Semua Status</option>
        <option>Aktif</option>
        <option>Nonaktif</option>
    </select>
</div>

{{-- Table --}}
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Pengguna</th>
                <th>Peran</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:center;">Total Analisis</th>
                <th>Bergabung</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users ?? [] as $user)
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--color-primary),var(--color-secondary));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <span style="font-size:0.875rem;font-weight:700;color:#FFF;">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        </div>
                        <div>
                            <p style="font-size:0.875rem;font-weight:600;color:var(--color-navy);">{{ $user->name }}</p>
                            <p style="font-size:0.75rem;color:var(--color-text-muted);">{{ $user->email }}</p>
                        </div>
                    </div>
                </td>
                <td>
                    @php $role = $user->getRoleNames()->first() ?? 'viewer'; @endphp
                    @if($role === 'admin')
                        <span class="badge badge-primary">Admin</span>
                    @elseif($role === 'analyst')
                        <span class="badge badge-warning">Analyst</span>
                    @else
                        <span class="badge badge-safe">Viewer</span>
                    @endif
                </td>
                <td style="text-align:center;">
                    @if($user->is_active)
                        <span class="badge badge-safe">Aktif</span>
                    @else
                        <span class="badge badge-hate">Nonaktif</span>
                    @endif
                </td>
                <td style="text-align:center;font-weight:700;color:var(--color-navy);">
                    {{ $user->analyses_count ?? $user->analyses()->count() }}
                </td>
                <td style="font-size:0.8125rem;color:var(--color-text-muted);">
                    {{ $user->created_at->format('d M Y') }}
                </td>
                <td style="text-align:right;">
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.375rem;">
                        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-ghost btn-sm" style="padding:0.35rem 0.625rem;" data-tooltip="Edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        @if(auth()->id() !== $user->id)
                        <button type="button" class="btn btn-ghost btn-sm" style="padding:0.35rem 0.625rem;color:var(--color-danger);" data-tooltip="Nonaktifkan">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="padding:3.5rem;text-align:center;">
                    <div style="display:flex;flex-direction:column;align-items:center;gap:0.875rem;">
                        <div style="width:52px;height:52px;border-radius:50%;background:var(--color-surface-2);display:flex;align-items:center;justify-content:center;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        </div>
                        <p style="font-weight:700;color:var(--color-navy);">Belum ada pengguna</p>
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">+ Tambah Pengguna</a>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($users) && $users->hasPages())
<div style="display:flex;justify-content:center;margin-top:1.5rem;">
    {{ $users->links() }}
</div>
@endif

@endsection
