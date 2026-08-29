@extends('layouts.auth')

@section('title', 'Masuk ke Portal')

@section('content')
<div style="min-height:100vh;display:grid;grid-template-columns:1fr 1fr;" class="auth-grid">

    {{-- ── LEFT PANEL: Ilustrasi Warm ── --}}
    <div style="background:linear-gradient(145deg,var(--color-navy) 0%,var(--color-navy-light) 60%,#1A4A60 100%);display:flex;flex-direction:column;justify-content:space-between;padding:3rem;position:relative;overflow:hidden;" class="auth-left">

        {{-- Background Blobs --}}
        <div style="position:absolute;top:-80px;left:-80px;width:300px;height:300px;border-radius:50%;background:rgba(231,111,81,0.12);"></div>
        <div style="position:absolute;bottom:-60px;right:-60px;width:240px;height:240px;border-radius:50%;background:rgba(42,157,143,0.10);"></div>
        <div style="position:absolute;top:50%;left:30%;width:180px;height:180px;border-radius:50%;background:rgba(244,162,97,0.06);"></div>

        <div style="position:relative;z-index:1;">
            {{-- Logo --}}
            <a href="{{ route('welcome') }}" style="display:inline-flex;align-items:center;gap:0.625rem;text-decoration:none;margin-bottom:4rem;">
                <div style="width:36px;height:36px;border-radius:0.625rem;background:linear-gradient(135deg,#E76F51,#F4A261);display:flex;align-items:center;justify-content:center;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFF" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                <span style="font-weight:800;font-size:1rem;color:#FFFFFF;">HateSense <span style="color:#F4A261;">ID</span></span>
            </a>

            {{-- Tagline --}}
            <h1 style="font-size:clamp(1.625rem,3vw,2.25rem);font-weight:800;color:#FFFFFF;line-height:1.25;margin-bottom:1rem;">
                Platform Riset<br>
                <span style="color:#F4A261;">Deteksi Ujaran</span><br>
                Kebencian
            </h1>
            <p style="font-size:0.9375rem;color:rgba(255,255,255,0.6);line-height:1.7;max-width:380px;">
                Analisis sentimen postingan X & Threads menggunakan Hierarchical IndoBERT — model AI bahasa Indonesia terdepan.
            </p>
        </div>

        {{-- Stats Card --}}
        <div style="position:relative;z-index:1;">
            <div style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:1.25rem;padding:1.25rem;backdrop-filter:blur(8px);">
                <div style="display:flex;justify-content:space-around;gap:1rem;">
                    <div style="text-align:center;">
                        <p style="font-size:1.375rem;font-weight:800;color:#F4A261;">6</p>
                        <p style="font-size:0.75rem;color:rgba(255,255,255,0.55);font-weight:500;">Kategori</p>
                    </div>
                    <div style="width:1px;background:rgba(255,255,255,0.12);"></div>
                    <div style="text-align:center;">
                        <p style="font-size:1.375rem;font-weight:800;color:#E76F51;">2</p>
                        <p style="font-size:0.75rem;color:rgba(255,255,255,0.55);font-weight:500;">Platform</p>
                    </div>
                    <div style="width:1px;background:rgba(255,255,255,0.12);"></div>
                    <div style="text-align:center;">
                        <p style="font-size:1.375rem;font-weight:800;color:#3DBDAD;">AI</p>
                        <p style="font-size:0.75rem;color:rgba(255,255,255,0.55);font-weight:500;">IndoBERT</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT PANEL: Login Form ── --}}
    <div style="display:flex;align-items:center;justify-content:center;padding:2rem;background:var(--color-canvas);">
        <div style="width:100%;max-width:420px;">

            {{-- Header --}}
            <div style="margin-bottom:2rem;">
                <h2 style="font-size:1.625rem;font-weight:800;color:var(--color-navy);margin-bottom:0.375rem;">Selamat Datang 👋</h2>
                <p style="font-size:0.9rem;color:var(--color-text-muted);">Masuk menggunakan akun yang diberikan oleh administrator.</p>
            </div>

            {{-- Error Validation --}}
            @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom:1.25rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>
                    @foreach($errors->all() as $error)
                    <p style="font-size:0.875rem;">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Login Form --}}
            <form method="POST" action="{{ route('login.submit') }}" style="display:flex;flex-direction:column;gap:1.125rem;">
                @csrf

                {{-- Email --}}
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email"
                           type="email"
                           name="email"
                           class="input {{ $errors->has('email') ? 'error' : '' }}"
                           value="{{ old('email') }}"
                           placeholder="nama@contoh.com"
                           autocomplete="email"
                           required>
                </div>

                {{-- Password --}}
                <div x-data="{ show: false }">
                    <label class="label" for="password">Kata Sandi</label>
                    <div style="position:relative;">
                        <input id="password"
                               :type="show ? 'text' : 'password'"
                               name="password"
                               class="input {{ $errors->has('password') ? 'error' : '' }}"
                               placeholder="Masukkan kata sandi"
                               autocomplete="current-password"
                               style="padding-right:3rem;"
                               required>
                        <button type="button"
                                @click="show = !show"
                                style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-muted);padding:0.25rem;">
                            <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Remember Me --}}
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-size:0.875rem;color:var(--color-text-body);">
                        <input type="checkbox" name="remember" id="remember" style="width:15px;height:15px;accent-color:var(--color-primary);">
                        Ingat saya
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Masuk ke Portal
                </button>
            </form>

            {{-- Divider --}}
            <div style="position:relative;margin:1.5rem 0;text-align:center;">
                <hr class="divider">
                <span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--color-canvas);padding:0 0.75rem;font-size:0.75rem;color:var(--color-text-subtle);font-weight:500;">AKUN DEMO</span>
            </div>

            {{-- Quick Login Demo --}}
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <p style="font-size:0.75rem;font-weight:600;color:var(--color-text-muted);margin-bottom:0.25rem;">Isi otomatis untuk demonstrasi:</p>
                <button type="button" onclick="fillLogin('admin@hatespeech.test','password')"
                        class="btn btn-ghost btn-sm"
                        style="justify-content:flex-start;gap:0.75rem;border-color:var(--color-border);">
                    <span class="badge badge-navy" style="font-size:0.6875rem;">Admin</span>
                    <span style="font-size:0.8125rem;color:var(--color-text-muted);">admin@hatespeech.test</span>
                </button>
                <button type="button" onclick="fillLogin('analyst@hatespeech.test','password')"
                        class="btn btn-ghost btn-sm"
                        style="justify-content:flex-start;gap:0.75rem;border-color:var(--color-border);">
                    <span class="badge badge-primary" style="font-size:0.6875rem;">Analyst</span>
                    <span style="font-size:0.8125rem;color:var(--color-text-muted);">analyst@hatespeech.test</span>
                </button>
                <button type="button" onclick="fillLogin('viewer@hatespeech.test','password')"
                        class="btn btn-ghost btn-sm"
                        style="justify-content:flex-start;gap:0.75rem;border-color:var(--color-border);">
                    <span class="badge badge-safe" style="font-size:0.6875rem;">Viewer</span>
                    <span style="font-size:0.8125rem;color:var(--color-text-muted);">viewer@hatespeech.test</span>
                </button>
            </div>

            {{-- Back to Landing --}}
            <p style="text-align:center;margin-top:1.75rem;font-size:0.8125rem;color:var(--color-text-muted);">
                <a href="{{ route('welcome') }}" style="color:var(--color-primary);font-weight:600;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">← Kembali ke Beranda</a>
            </p>

        </div>
    </div>
</div>

<script>
function fillLogin(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
    document.getElementById('email').dispatchEvent(new Event('input'));
    document.getElementById('password').dispatchEvent(new Event('input'));
}
</script>

<style>
@media (max-width: 768px) {
    .auth-grid { grid-template-columns: 1fr !important; }
    .auth-left { display: none !important; }
}
</style>
@endsection
