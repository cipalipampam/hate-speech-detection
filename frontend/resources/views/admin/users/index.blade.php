@extends('layouts.app')

@section('title', '§ 05.0 Kelola Pengguna')

@section('breadcrumb')
<span style="color:#0A0A0A;">§ 05.0 PENGGUNA</span>
@endsection

@section('content')

{{-- Monograph Header --}}
<div style="border-bottom:2px solid #0A0A0A;padding-bottom:1.25rem;margin-bottom:2rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
            <span class="badge badge-black">SEKSI § 05.0</span>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">OTORISASI & PERSONEL RISET</span>
        </div>
        <h1 style="font-size:2.25rem;font-weight:900;letter-spacing:-0.035em;color:#0A0A0A;margin:0;line-height:1.1;">
            DIREKTORI PENGGUNA
        </h1>
        <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
            Manajemen akun peneliti, penetapan peran (role), dan status lisensi akses sistem.
        </p>
    </div>

    <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-lg">
        <span>+ TAMBAH PENGGUNA</span>
    </a>
</div>

{{-- Role Summary Stat Blocks --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="stat-block">
        <span class="stat-block-label">ADMINISTRATOR</span>
        <span class="stat-block-val">{{ $roleCounts['admin'] ?? 0 }}</span>
        <div class="stat-block-meta">[HAK AKSES PENUH]</div>
    </div>

    <div class="stat-block" style="border-top:3px solid var(--color-primary);">
        <span class="stat-block-label" style="color:var(--color-primary);">PENELITI (ANALYST)</span>
        <span class="stat-block-val" style="color:var(--color-primary);">{{ $roleCounts['analyst'] ?? 0 }}</span>
        <div class="stat-block-meta" style="border-color:var(--color-primary);color:var(--color-primary);">[RUN PIPELINE]</div>
    </div>

    <div class="stat-block">
        <span class="stat-block-label">PENGAMAT (VIEWER)</span>
        <span class="stat-block-val">{{ $roleCounts['viewer'] ?? 0 }}</span>
        <div class="stat-block-meta">[READ ONLY]</div>
    </div>

    <div class="stat-block" style="background:var(--color-surface-2);">
        <span class="stat-block-label">TOTAL AKUN AKTIF</span>
        <span class="stat-block-val">{{ $activeCount ?? 0 }}</span>
        <div class="stat-block-meta">[STATUS: ACTIVE]</div>
    </div>
</div>

{{-- Functional Filter Toolbar --}}
<div class="card" style="padding:0.75rem 1rem;margin-bottom:1.5rem;">
    <form method="GET" action="{{ route('admin.users.index') }}" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin:0;">
        <div style="flex:1;min-width:240px;position:relative;">
            <input type="text"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Cari nama atau email pengguna..."
                   class="input"
                   style="height:38px;padding-left:0.75rem;font-size:0.8125rem;">
        </div>

        <select name="role" onchange="this.form.submit()" class="input" style="width:auto;height:38px;font-size:0.8125rem;cursor:pointer;">
            <option value="all">SEMUA PERAN</option>
            <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>ADMIN</option>
            <option value="analyst" {{ request('role') === 'analyst' ? 'selected' : '' }}>ANALYST</option>
            <option value="viewer" {{ request('role') === 'viewer' ? 'selected' : '' }}>VIEWER</option>
        </select>

        <select name="status" onchange="this.form.submit()" class="input" style="width:auto;height:38px;font-size:0.8125rem;cursor:pointer;">
            <option value="all">SEMUA STATUS</option>
            <option value="active" {{ in_array(request('status'), ['active', 'aktif', '1'], true) ? 'selected' : '' }}>AKTIF</option>
            <option value="inactive" {{ in_array(request('status'), ['inactive', 'nonaktif', '0'], true) ? 'selected' : '' }}>NONAKTIF</option>
        </select>

        <button type="submit" class="btn btn-primary" style="height:38px;padding:0 1.25rem;font-size:0.8125rem;font-weight:800;">
            CARI
        </button>
    </form>
</div>

{{-- Swiss Monograph Users Table --}}
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th style="width:260px;">PENGGUNA & IDENTITAS</th>
                <th style="width:140px;text-align:center;">PERAN (ROLE)</th>
                <th style="width:110px;text-align:center;">STATUS</th>
                <th style="width:120px;text-align:center;">TOTAL RISET</th>
                <th>TANGGAL BERGABUNG</th>
                <th style="width:120px;text-align:right;">AKSI</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:0.625rem;">
                        <div style="width:32px;height:32px;background:#0A0A0A;color:#FFFFFF;display:flex;align-items:center;justify-content:center;font-family:var(--font-mono);font-size:0.8125rem;font-weight:900;flex-shrink:0;">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <div>
                            <span style="font-weight:800;color:#0A0A0A;display:block;font-size:0.875rem;">{{ $user->name }}</span>
                            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">{{ $user->email }}</span>
                        </div>
                    </div>
                </td>
                <td style="text-align:center;">
                    @php $role = $user->getRoleNames()->first() ?? 'viewer'; @endphp
                    @if($role === 'admin')
                        <span class="badge badge-black" style="font-size:0.65rem;">ADMIN</span>
                    @elseif($role === 'analyst')
                        <span class="badge badge-primary" style="font-size:0.65rem;">ANALYST</span>
                    @else
                        <span class="badge badge-mono" style="font-size:0.65rem;">VIEWER</span>
                    @endif
                </td>
                <td style="text-align:center;">
                    @if($user->is_active)
                        <span class="badge badge-safe" style="font-size:0.65rem;">AKTIF</span>
                    @else
                        <span class="badge badge-hate" style="font-size:0.65rem;">NONAKTIF</span>
                    @endif
                </td>
                <td style="text-align:center;font-family:var(--font-mono);font-weight:800;color:#0A0A0A;">
                    {{ $user->analyses_count ?? 0 }}
                </td>
                <td style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">
                    {{ $user->created_at ? $user->created_at->format('d M Y, H:i') : '—' }}
                </td>
                <td style="text-align:right;">
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.375rem;">
                        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-outline btn-sm" style="font-size:0.6875rem;padding:0.25rem 0.5rem;" title="Edit Pengguna">
                            EDIT
                        </a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="padding:4rem 1rem;text-align:center;font-family:var(--font-mono);color:var(--color-text-muted);">
                    [TIDAK DITEMUKAN PENGGUNA SESUAI DENGAN FILTER]
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Pagination Footer --}}
    @if(isset($users) && $users->hasPages())
    <div style="padding:0.75rem 1rem;background:#FFFFFF;border-top:1px solid #0A0A0A;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;font-family:var(--font-mono);font-size:0.75rem;">
        <span>
            MENAMPILKAN <strong>{{ $users->firstItem() ?? 0 }}</strong>–<strong>{{ $users->lastItem() ?? 0 }}</strong> DARI <strong>{{ $users->total() }}</strong> PENGGUNA
        </span>
        <div>
            {{ $users->links() }}
        </div>
    </div>
    @endif
</div>

@endsection
