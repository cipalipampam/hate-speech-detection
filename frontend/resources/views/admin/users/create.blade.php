@extends('layouts.app')

@section('title', '§ 05.1 Add User')

@section('breadcrumb')
<a href="{{ route('admin.users.index') }}" style="color:var(--color-text-muted);text-decoration:none;">§ 05.0 USER MANAGEMENT</a>
<span>/</span>
<span style="color:#0A0A0A;">+ ADD USER</span>
@endsection

@section('content')
<div style="max-width:620px;margin:0 auto;">

    {{-- Monograph Section Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:1.75rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
            <span class="badge badge-black">SECTION § 05.1</span>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">RESEARCH PERSONNEL REGISTRATION</span>
        </div>
        <h1 style="font-size:2rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0;line-height:1.1;">
            ADD NEW USER
        </h1>
        <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
            Create a researcher account and assign system access permissions.
        </p>
    </div>

    @if($errors->any())
    <div style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;padding:0.875rem 1rem;margin-bottom:1.5rem;">
        <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin:0 0 0.25rem;">VALIDATION FAILED:</p>
        <ul style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;padding-left:1.25rem;">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card" style="padding:1.5rem;">
        <form method="POST" action="{{ route('admin.users.store') }}" style="display:flex;flex-direction:column;gap:1.25rem;">
            @csrf

            <div>
                <label class="label" for="name">Full Name <span style="color:var(--color-crimson);">*</span></label>
                <input id="name" type="text" name="name" value="{{ old('name') }}"
                       class="input {{ $errors->has('name') ? 'error' : '' }}"
                       placeholder="cth: Dr. Rian Pratama"
                       style="height:42px;" required>
                @error('name')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="email">Email Address <span style="color:var(--color-crimson);">*</span></label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       class="input {{ $errors->has('email') ? 'error' : '' }}"
                       placeholder="cth: rian@hatespeech.test"
                       style="height:42px;" required>
                @error('email')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="role">Authorization Role <span style="color:var(--color-crimson);">*</span></label>
                <select id="role" name="role" class="input" style="height:42px;cursor:pointer;">
                    @foreach($roles as $role)
                    <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
                        {{ strtoupper($role->name) }} — {{ $role->name === 'admin' ? 'Administrator (Full Access)' : 'Analyst (Run Analysis & Export)' }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="password">Password <span style="color:var(--color-crimson);">*</span></label>
                <input id="password" type="password" name="password"
                       class="input {{ $errors->has('password') ? 'error' : '' }}"
                       placeholder="Minimum 8 characters"
                       style="height:42px;" required>
                @error('password')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="password_confirmation">Confirm Password <span style="color:var(--color-crimson);">*</span></label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       class="input"
                       placeholder="Re-enter the password"
                       style="height:42px;" required>
            </div>

            <div style="display:flex;gap:0.75rem;justify-content:space-between;border-top:1px solid var(--color-border);padding-top:1.25rem;margin-top:0.5rem;">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">← CANCEL</a>
                <button type="submit" class="btn btn-primary">+ CREATE USER</button>
            </div>
        </form>
    </div>

</div>
@endsection
