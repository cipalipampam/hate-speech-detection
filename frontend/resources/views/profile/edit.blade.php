@extends('layouts.app')

@section('title', 'Profil Saya')

@section('breadcrumb')
<span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">Profil Saya</span>
@endsection

@section('content')

<div style="max-width:640px;margin:0 auto;">

    <div class="page-header">
        <h1 class="page-title">Profil Saya</h1>
        <p class="page-subtitle">Perbarui informasi akun dan kata sandi Anda.</p>
    </div>

    {{-- Avatar + Info Card --}}
    <div class="card" style="padding:1.5rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:1.25rem;">
        <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--color-primary),var(--color-secondary));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <span style="font-size:1.5rem;font-weight:800;color:#FFF;">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
        </div>
        <div>
            <p style="font-size:1.125rem;font-weight:700;color:var(--color-navy);">{{ auth()->user()->name }}</p>
            <p style="font-size:0.875rem;color:var(--color-text-muted);">{{ auth()->user()->email }}</p>
            <span class="badge badge-navy" style="margin-top:0.375rem;text-transform:capitalize;">{{ auth()->user()->getRoleNames()->first() ?? 'viewer' }}</span>
        </div>
    </div>

    {{-- Update Info Form --}}
    <div class="card" style="padding:1.5rem;margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--color-border);">
            <div style="width:34px;height:34px;border-radius:0.625rem;background:var(--color-primary-bg);display:flex;align-items:center;justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <p style="font-size:0.9375rem;font-weight:700;color:var(--color-navy);">Informasi Akun</p>
        </div>

        @if(session('profile_success'))
        <div class="alert alert-success" style="margin-bottom:1.25rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            {{ session('profile_success') }}
        </div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" style="display:flex;flex-direction:column;gap:1rem;">
            @csrf
            @method('PATCH')

            <div>
                <label class="label" for="name">Nama Lengkap</label>
                <input id="name" type="text" name="name" value="{{ old('name', auth()->user()->name) }}"
                       class="input {{ $errors->has('name') ? 'error' : '' }}" required>
                @error('name')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}"
                       class="input {{ $errors->has('email') ? 'error' : '' }}" required>
                @error('email')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn btn-primary" style="align-self:flex-start;">
                Simpan Perubahan
            </button>
        </form>
    </div>

    {{-- Ganti Password --}}
    <div class="card" style="padding:1.5rem;" x-data="{ showFields: false }">
        <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" @click="showFields = !showFields">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:34px;height:34px;border-radius:0.625rem;background:#EEF3F8;display:flex;align-items:center;justify-content:center;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-navy)" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <p style="font-size:0.9375rem;font-weight:700;color:var(--color-navy);">Ganti Kata Sandi</p>
            </div>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2"
                 :style="showFields ? 'transform:rotate(180deg)' : ''" style="transition:transform 0.2s;">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </div>

        <div x-show="showFields" x-cloak x-transition style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--color-border);">
            <form method="POST" action="{{ route('profile.password') }}" style="display:flex;flex-direction:column;gap:1rem;">
                @csrf
                @method('PATCH')

                <div>
                    <label class="label" for="current_password">Kata Sandi Saat Ini</label>
                    <input id="current_password" type="password" name="current_password"
                           class="input {{ $errors->has('current_password') ? 'error' : '' }}"
                           placeholder="Masukkan kata sandi saat ini">
                    @error('current_password')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="password">Kata Sandi Baru</label>
                    <input id="password" type="password" name="password"
                           class="input {{ $errors->has('password') ? 'error' : '' }}"
                           placeholder="Minimal 8 karakter">
                    @error('password')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="password_confirmation">Konfirmasi Kata Sandi Baru</label>
                    <input id="password_confirmation" type="password" name="password_confirmation"
                           class="input" placeholder="Ulangi kata sandi baru">
                </div>

                <button type="submit" class="btn btn-navy" style="align-self:flex-start;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Perbarui Kata Sandi
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
