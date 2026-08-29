@extends('layouts.app')
@section('title', 'Tambah Pengguna')
@section('breadcrumb')
<div style="display:flex;align-items:center;gap:0.5rem;">
    <a href="{{ route('admin.users.index') }}" style="font-size:0.875rem;color:var(--color-text-muted);font-weight:500;text-decoration:none;">Kelola Pengguna</a>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-subtle)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">Tambah</span>
</div>
@endsection
@section('content')
<div style="max-width:560px;margin:0 auto;">
    <div class="page-header"><h1 class="page-title">Tambah Pengguna</h1></div>
    <div class="card" style="padding:1.5rem;">
        <form method="POST" action="{{ route('admin.users.store') }}" style="display:flex;flex-direction:column;gap:1.125rem;">
            @csrf
            <div>
                <label class="label" for="name">Nama Lengkap</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" class="input {{ $errors->has('name') ? 'error' : '' }}" required>
                @error('name')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input {{ $errors->has('email') ? 'error' : '' }}" required>
                @error('email')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="role">Peran</label>
                <select id="role" name="role" class="input" style="cursor:pointer;">
                    @foreach($roles as $role)
                    <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="password">Kata Sandi</label>
                <input id="password" type="password" name="password" class="input {{ $errors->has('password') ? 'error' : '' }}" required>
                @error('password')<p style="font-size:0.75rem;color:var(--color-danger);margin-top:0.25rem;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">Konfirmasi Kata Sandi</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" required>
            </div>
            <div style="display:flex;gap:0.75rem;">
                <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">← Batal</a>
                <button type="submit" class="btn btn-primary">Buat Pengguna</button>
            </div>
        </form>
    </div>
</div>
@endsection
