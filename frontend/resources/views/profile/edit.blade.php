@extends('layouts.app')

@section('title', '§ 00.0 Profile Settings')

@section('breadcrumb')
<span style="color:#0A0A0A;">§ 00.0 PROFILE SETTINGS</span>
@endsection

@section('content')

<div style="max-width:680px;margin:0 auto;">

    {{-- Monograph Section Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:1.75rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
            <span class="badge badge-black">SECTION § 00.0</span>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">RESEARCHER ACCOUNT</span>
        </div>
        <h1 style="font-size:2rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0;line-height:1.1;">
            PROFILE SETTINGS
        </h1>
        <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
            Manage researcher identity details and account password security.
        </p>
    </div>

    {{-- Avatar + Identity Card (Swiss Monograph Boxy) --}}
    <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.25rem;">
        <div style="width:56px;height:56px;background:#0A0A0A;color:#FFFFFF;border:2px solid #0A0A0A;box-shadow:3px 3px 0 #0A0A0A;display:flex;align-items:center;justify-content:center;font-family:var(--font-mono);font-size:1.5rem;font-weight:900;flex-shrink:0;">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </div>
        <div style="flex:1;">
            <p style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0 0 2px;">{{ auth()->user()->name }}</p>
            <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0 0 6px;">{{ auth()->user()->email }}</p>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span class="badge badge-black" style="font-size:0.625rem;">
                    ROLE: {{ strtoupper(auth()->user()->getRoleNames()->first() ?? 'ANALYST') }}
                </span>
                <span class="badge badge-safe" style="font-size:0.625rem;">STATUS: ACTIVE</span>
            </div>
        </div>
    </div>

    {{-- Form 1: Ubah Informasi Akun --}}
    <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">
        <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.625rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;">
            <span class="stat-block-label">01. IDENTITY INFORMATION</span>
            <span class="badge badge-mono">PRIMARY DATA</span>
        </div>

        @if(session('profile_success'))
        <div style="background:var(--color-primary-bg);border:2px solid var(--color-primary);box-shadow:3px 3px 0 #0A0A0A;padding:0.75rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.625rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            <p style="font-size:0.8125rem;font-weight:700;color:#0A0A0A;margin:0;">{{ session('profile_success') }}</p>
        </div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" style="display:flex;flex-direction:column;gap:1.125rem;">
            @csrf
            @method('PATCH')

            <div>
                <label class="label" for="name">Full Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', auth()->user()->name) }}"
                       class="input {{ $errors->has('name') ? 'error' : '' }}"
                       style="height:42px;" required>
                @error('name')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="email">Email Address</label>
                <input id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}"
                       class="input {{ $errors->has('email') ? 'error' : '' }}"
                       style="height:42px;" required>
                @error('email')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>

            <div style="border-top:1px solid var(--color-border);padding-top:1rem;margin-top:0.25rem;">
                <button type="submit" class="btn btn-primary">
                    SAVE IDENTITY CHANGES
                </button>
            </div>
        </form>
    </div>

    {{-- Form 2: Update Password --}}
    <div class="card" style="padding:1.5rem;" x-data="{ showFields: false }">
        <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" @click="showFields = !showFields">
            <div>
                <span class="stat-block-label" style="margin:0 0 2px;">02. ACCESS SECURITY</span>
                <p style="font-size:1rem;font-weight:900;color:#0A0A0A;margin:0;">Update Password</p>
            </div>
            <button type="button" class="btn btn-outline btn-sm" style="font-size:0.6875rem;">
                <span x-text="showFields ? 'CLOSE FORM [-]' : 'OPEN FORM [+]'"></span>
            </button>
        </div>

        <div x-show="showFields" x-cloak style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #0A0A0A;">
            <form method="POST" action="{{ route('profile.password') }}" style="display:flex;flex-direction:column;gap:1.125rem;">
                @csrf
                @method('PATCH')

                <div>
                    <label class="label" for="current_password">Current Password</label>
                    <input id="current_password" type="password" name="current_password"
                           class="input {{ $errors->has('current_password') ? 'error' : '' }}"
                           placeholder="Enter your current password"
                           style="height:42px;">
                    @error('current_password')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="new_password">New Password</label>
                    <input id="new_password" type="password" name="password"
                           class="input {{ $errors->has('password') ? 'error' : '' }}"
                           placeholder="Minimum 8 characters"
                           style="height:42px;">
                    @error('password')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="new_password_confirmation">Confirm New Password</label>
                    <input id="new_password_confirmation" type="password" name="password_confirmation"
                           class="input"
                           placeholder="Re-enter the new password"
                           style="height:42px;">
                </div>

                <div style="border-top:1px solid var(--color-border);padding-top:1rem;margin-top:0.25rem;">
                    <button type="submit" class="btn btn-primary">
                        UPDATE PASSWORD
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
