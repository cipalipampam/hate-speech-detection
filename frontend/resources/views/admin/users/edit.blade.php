@extends('layouts.app')
@section('title', 'Edit Pengguna')
@section('content')
<div style="max-width:560px;margin:0 auto;">
    <div class="page-header">
        <h1 class="page-title">Edit Pengguna</h1>
        <p class="page-subtitle">{{ $user->name }}</p>
    </div>
    <div class="card" style="padding:1.5rem;">
        @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:1.25rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            {{ session('success') }}
        </div>
        @endif
        <form method="POST" action="{{ route('admin.users.update', $user->id) }}" style="display:flex;flex-direction:column;gap:1.125rem;">
            @csrf
            @method('PATCH')
            <div>
                <label class="label" for="name">Nama Lengkap</label>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" class="input {{ $errors->has('name') ? 'error' : '' }}" required>
            </div>
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="input {{ $errors->has('email') ? 'error' : '' }}" required>
            </div>
            <div>
                <label class="label" for="role">Peran</label>
                <select id="role" name="role" class="input" style="cursor:pointer;">
                    @foreach($roles as $role)
                    <option value="{{ $role->name }}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:0.75rem;justify-content:space-between;">
                <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">← Kembali</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
