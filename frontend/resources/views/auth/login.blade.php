@extends('layouts.auth')

@section('title', 'Masuk ke Portal Riset')

@section('content')
{{-- ═══════════════════════════════════════════════════════════
     MONOGRAPH TOP MASTHEAD
════════════════════════════════════════════════════════════ --}}
<header class="masthead">
    <div style="display:flex;align-items:center;height:100%;">
        <a href="{{ route('welcome') }}" style="display:flex;align-items:center;gap:0.75rem;text-decoration:none;padding-right:1.25rem;border-right:1px solid var(--color-border);height:100%;">
            <div style="background:#0A0A0A;color:#FFFFFF;font-family:var(--font-mono);font-weight:900;font-size:0.875rem;padding:0.25rem 0.5rem;line-height:1;border:1px solid #0A0A0A;">
                HS
            </div>
            <div>
                <span style="font-weight:900;font-size:0.9375rem;color:#0A0A0A;letter-spacing:-0.03em;display:block;line-height:1.1;">
                    HATESENSE <span style="color:var(--color-primary);">ID LAB</span>
                </span>
                <span style="font-family:var(--font-mono);font-size:0.625rem;color:var(--color-text-muted);letter-spacing:0.04em;display:block;">
                    PORTAL AUTENTIKASI RISET
                </span>
            </div>
        </a>

        <div class="hidden sm:flex" style="align-items:center;gap:0.5rem;padding-left:1rem;">
            <span class="badge badge-black" style="font-size:0.625rem;">MODUL § 00.1</span>
            <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">SISTEM DETEKSI UJARAN KEBENCIAN</span>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:0.75rem;">
        <div class="badge badge-mono hidden md:inline-flex" style="font-size:0.6875rem;gap:0.35rem;" title="Status Server: Normal">
            <span style="width:6px;height:6px;background:var(--color-teal);border-radius:50%;display:inline-block;"></span>
            <span>FASTAPI · AKTIF</span>
        </div>

        <a href="{{ route('welcome') }}" class="btn btn-outline btn-sm">
            <span>← KEMBALI KE BERANDA</span>
        </a>
    </div>
</header>

{{-- ═══════════════════════════════════════════════════════════
     MAIN AUTHENTICATION CONTAINER
════════════════════════════════════════════════════════════ --}}
<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:4.25rem 1.25rem 1.5rem;background-color:var(--color-canvas);box-sizing:border-box;">
    <div style="max-width:980px;width:100%;">

        {{-- Monograph Dossier Card --}}
        <div class="card" style="background:#FFFFFF;border:2px solid #0A0A0A;box-shadow:6px 6px 0 #0A0A0A;display:grid;grid-template-columns:1fr 1.18fr;" class="login-dossier-grid">

            {{-- ── LEFT PANEL: Swiss Monograph Editorial Specs ── --}}
            <div style="background:var(--color-surface-2);border-right:2px solid #0A0A0A;padding:1.75rem 1.75rem;display:flex;flex-direction:column;justify-content:space-between;box-sizing:border-box;" class="login-left-pane">
                <div>
                    {{-- Section Tag --}}
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.875rem;">
                        <span class="badge badge-black" style="font-size:0.625rem;">DOSIR § 00.1</span>
                        <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);letter-spacing:0.04em;">GERBANG PENELITI</span>
                    </div>

                    {{-- Main Headline --}}
                    <h1 style="font-size:1.5rem;font-weight:900;letter-spacing:-0.035em;line-height:1.15;color:#0A0A0A;margin:0 0 0.75rem;">
                        SISTEM DETEKSI<br>
                        <span style="background:var(--color-danger);padding:0 0.25rem;border:1.5px solid #0A0A0A;display:inline-block;margin-top:2px;">UJARAN KEBENCIAN</span><br>
                        MULTI-PLATFORM
                    </h1>

                    <p style="font-size:0.8125rem;line-height:1.6;color:#262626;margin:0 0 1.25rem;">
                        Otentikasi konsol telemetri untuk investigasi hate speech pada <strong style="color:#0A0A0A;">Twitter (𝕏)</strong> dan <strong style="color:#0A0A0A;">Threads</strong> berbasis model <strong style="color:#0A0A0A;">Hierarchical IndoBERT</strong>.
                    </p>

                    {{-- Technical Specifications Card --}}
                    <div style="background:#FFFFFF;border:1px solid #0A0A0A;box-shadow:2px 2px 0 #0A0A0A;padding:0.875rem 1rem;margin-bottom:1rem;">
                        <div style="border-bottom:1px solid var(--color-border-subtle);padding-bottom:0.375rem;margin-bottom:0.625rem;display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;color:#0A0A0A;letter-spacing:0.04em;">SPESIFIKASI ENGINE NLP</span>
                            <span class="badge badge-safe" style="font-size:0.5625rem;padding:1px 4px;">v2.4</span>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:0.45rem;font-family:var(--font-mono);font-size:0.7188rem;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--color-text-muted);">MODEL INTI:</span>
                                <span style="font-weight:700;color:#0A0A0A;">Hierarchical IndoBERT</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--color-text-muted);">KAMUS SLANG:</span>
                                <span style="font-weight:700;color:#0A0A0A;">15.167 Kamusalay</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--color-text-muted);">TAKSONOMI:</span>
                                <span style="font-weight:700;color:#0A0A0A;">6 Kategori Spesifik L2</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--color-text-muted);">KORPUS:</span>
                                <span style="font-weight:700;color:#0A0A0A;">𝕏 & ⊙ Crawler</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Security & Protocol Notice --}}
                <div style="border:1px solid var(--color-border);background:#FFFFFF;padding:0.75rem 0.875rem;border-left:3px solid var(--color-primary);margin-top:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.35rem;margin-bottom:0.2rem;">
                        <span style="font-family:var(--font-mono);font-size:0.625rem;font-weight:800;color:var(--color-primary);letter-spacing:0.04em;">PROTOKOL KEAMANAN LAB</span>
                    </div>
                    <p style="font-family:var(--font-mono);font-size:0.6563rem;line-height:1.45;color:var(--color-text-muted);margin:0;">
                        Seluruh aktivitas analisis dan investigasi teks tercatat pada audit log forensik HateSense ID Lab.
                    </p>
                </div>
            </div>

            {{-- ── RIGHT PANEL: Clean Academic Login Form ── --}}
            <div style="background:#FFFFFF;padding:1.75rem 2rem 1.5rem;display:flex;flex-direction:column;justify-content:space-between;box-sizing:border-box;">
                <div>
                    {{-- Form Header --}}
                    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:0.75rem;margin-bottom:1.125rem;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;">
                        <div>
                            <span class="stat-block-label" style="display:block;margin-bottom:0.2rem;font-size:0.625rem;">AUTENTIKASI RISET</span>
                            <h2 style="font-size:1.25rem;font-weight:900;color:#0A0A0A;margin:0;letter-spacing:-0.03em;">
                                MASUK KE PORTAL
                            </h2>
                        </div>
                        <span class="badge badge-safe" style="font-size:0.625rem;white-space:nowrap;">
                            AKUN PENELITI
                        </span>
                    </div>

                    {{-- Error Alert --}}
                    @if($errors->any())
                    <div style="background:var(--color-crimson-bg);border:1px solid var(--color-crimson);padding:0.625rem 0.875rem;margin-bottom:1rem;display:flex;gap:0.625rem;align-items:flex-start;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-crimson)" stroke-width="2.5" style="flex-shrink:0;margin-top:2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div>
                            @foreach($errors->all() as $error)
                            <p style="font-size:0.75rem;color:var(--color-crimson);margin:0;font-family:var(--font-mono);line-height:1.4;">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Authentication Form --}}
                    <form method="POST" action="{{ route('login.submit') }}" style="display:flex;flex-direction:column;gap:0.875rem;">
                        @csrf

                        {{-- Email Field --}}
                        <div>
                            <label class="label" for="email" style="font-size:0.6875rem;margin-bottom:0.25rem;">ALAMAT EMAIL</label>
                            <input id="email"
                                   type="email"
                                   name="email"
                                   class="input {{ $errors->has('email') ? 'error' : '' }}"
                                   value="{{ old('email') }}"
                                   placeholder="nama@hatespeech.test"
                                   autocomplete="email"
                                   style="padding:0.45rem 0.65rem;font-size:0.8125rem;"
                                   required
                                   autofocus>
                        </div>

                        {{-- Password Field --}}
                        <div x-data="{ show: false }">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                                <label class="label" for="password" style="margin-bottom:0;font-size:0.6875rem;">KATA SANDI</label>
                                <span style="font-family:var(--font-mono);font-size:0.5938rem;color:var(--color-text-subtle);">BCRYPT PROTECTED</span>
                            </div>
                            <div style="position:relative;">
                                <input id="password"
                                       :type="show ? 'text' : 'password'"
                                       name="password"
                                       class="input {{ $errors->has('password') ? 'error' : '' }}"
                                       placeholder="••••••••••••"
                                       autocomplete="current-password"
                                       style="padding:0.45rem 2.5rem 0.45rem 0.65rem;font-size:0.8125rem;"
                                       required>
                                <button type="button"
                                        @click="show = !show"
                                        style="position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--color-text-muted);padding:0.25rem;display:flex;align-items:center;"
                                        title="Tampilkan / Sembunyikan Kata Sandi">
                                    <svg x-show="!show" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg x-show="show" x-cloak width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Remember Me Option --}}
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <label style="display:inline-flex;align-items:center;gap:0.4rem;cursor:pointer;font-family:var(--font-mono);font-size:0.6875rem;font-weight:700;color:var(--color-text-body);">
                                <input type="checkbox" name="remember" id="remember" style="width:14px;height:14px;accent-color:#0A0A0A;border-radius:0;cursor:pointer;">
                                <span>INGAT SESI SAYA</span>
                            </label>

                            <span style="font-family:var(--font-mono);font-size:0.625rem;color:var(--color-text-subtle);">
                                RBAC PROTOCOL
                            </span>
                        </div>

                        {{-- Submit Button --}}
                        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.25rem;padding:0.625rem;font-size:0.8125rem;box-shadow:3px 3px 0 var(--color-primary);">
                            <span>MASUK KE PORTAL [→]</span>
                        </button>
                    </form>
                </div>

                {{-- ── Interactive Quick Demo Presets ── --}}
                <div style="margin-top:1.25rem;border-top:1px dashed var(--color-border);padding-top:0.875rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                        <span class="stat-block-label" style="font-size:0.625rem;">AKUN DEMO CEPAT:</span>
                        <span style="font-family:var(--font-mono);font-size:0.5938rem;color:var(--color-text-muted);">KLIK UNTUK MENGISI FORM</span>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:0.4rem;" id="demo-preset-container">
                        {{-- Admin Demo Preset --}}
                        <button type="button"
                                onclick="selectDemoAccount('admin@hatespeech.test', 'password', this)"
                                class="demo-preset-btn"
                                style="width:100%;text-align:left;background:var(--color-surface-2);border:1px solid var(--color-border);padding:0.45rem 0.65rem;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:all 0.12s ease;border-radius:0;">
                            <div style="display:flex;align-items:center;gap:0.65rem;">
                                <span class="badge badge-black" style="font-size:0.5938rem;min-width:58px;justify-content:center;padding:1px 4px;">ADMIN</span>
                                <div>
                                    <span style="font-family:var(--font-mono);font-size:0.7188rem;font-weight:700;color:#0A0A0A;display:block;line-height:1.2;">admin@hatespeech.test</span>
                                    <span style="font-family:var(--font-mono);font-size:0.5938rem;color:var(--color-text-muted);">Akses Penuh + Manajemen User</span>
                                </div>
                            </div>
                            <span class="preset-action" style="font-family:var(--font-mono);font-size:0.625rem;font-weight:700;color:var(--color-primary);">[GUNAKAN]</span>
                        </button>

                        {{-- Analyst Demo Preset --}}
                        <button type="button"
                                onclick="selectDemoAccount('analyst@hatespeech.test', 'password', this)"
                                class="demo-preset-btn"
                                style="width:100%;text-align:left;background:var(--color-surface-2);border:1px solid var(--color-border);padding:0.45rem 0.65rem;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:all 0.12s ease;border-radius:0;">
                            <div style="display:flex;align-items:center;gap:0.65rem;">
                                <span class="badge badge-safe" style="font-size:0.5938rem;min-width:58px;justify-content:center;padding:1px 4px;">ANALYST</span>
                                <div>
                                    <span style="font-family:var(--font-mono);font-size:0.7188rem;font-weight:700;color:#0A0A0A;display:block;line-height:1.2;">analyst@hatespeech.test</span>
                                    <span style="font-family:var(--font-mono);font-size:0.5938rem;color:var(--color-text-muted);">Investigasi Korpus & Scraping</span>
                                </div>
                            </div>
                            <span class="preset-action" style="font-family:var(--font-mono);font-size:0.625rem;font-weight:700;color:var(--color-primary);">[GUNAKAN]</span>
                        </button>

                        {{-- Viewer Demo Preset --}}
                        <button type="button"
                                onclick="selectDemoAccount('viewer@hatespeech.test', 'password', this)"
                                class="demo-preset-btn"
                                style="width:100%;text-align:left;background:var(--color-surface-2);border:1px solid var(--color-border);padding:0.45rem 0.65rem;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:all 0.12s ease;border-radius:0;">
                            <div style="display:flex;align-items:center;gap:0.65rem;">
                                <span class="badge badge-mono" style="font-size:0.5938rem;min-width:58px;justify-content:center;padding:1px 4px;">VIEWER</span>
                                <div>
                                    <span style="font-family:var(--font-mono);font-size:0.7188rem;font-weight:700;color:#0A0A0A;display:block;line-height:1.2;">viewer@hatespeech.test</span>
                                    <span style="font-family:var(--font-mono);font-size:0.5938rem;color:var(--color-text-muted);">Lihat Analisis & Ekspor Data</span>
                                </div>
                            </div>
                            <span class="preset-action" style="font-family:var(--font-mono);font-size:0.625rem;font-weight:700;color:var(--color-primary);">[GUNAKAN]</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>

        {{-- Footer Telemetry Note --}}
        <div style="margin-top:1.25rem;display:flex;align-items:center;justify-content:space-between;font-family:var(--font-mono);font-size:0.625rem;color:var(--color-text-muted);flex-wrap:wrap;gap:0.5rem;padding:0 0.5rem;">
            <span>HATESENSE ID LAB © {{ date('Y') }} · TUGAS AKHIR INFORMATIKA</span>
            <span>KERANGKA NLP: INDOBERT-BASE-PHASE2-INDOSLAM</span>
        </div>

    </div>
</main>

<script>
function selectDemoAccount(email, password, btnElement) {
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    emailInput.value = email;
    passwordInput.value = password;

    emailInput.dispatchEvent(new Event('input', { bubbles: true }));
    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));

    // Reset styles on all demo buttons
    document.querySelectorAll('.demo-preset-btn').forEach(btn => {
        btn.style.borderColor = 'var(--color-border)';
        btn.style.backgroundColor = 'var(--color-surface-2)';
        btn.style.boxShadow = 'none';
        const actionSpan = btn.querySelector('.preset-action');
        if (actionSpan) {
            actionSpan.textContent = '[GUNAKAN]';
            actionSpan.style.color = 'var(--color-primary)';
        }
    });

    // Highlight active preset
    btnElement.style.borderColor = '#0A0A0A';
    btnElement.style.backgroundColor = '#FFFFFF';
    btnElement.style.boxShadow = '2px 2px 0 #0A0A0A';
    const activeSpan = btnElement.querySelector('.preset-action');
    if (activeSpan) {
        activeSpan.textContent = '✓ TERPILIH';
        activeSpan.style.color = 'var(--color-teal)';
    }

    // Flash focus feedback
    emailInput.focus();
}
</script>

<style>
.demo-preset-btn:hover {
    background-color: #FFFFFF !important;
    border-color: #0A0A0A !important;
    box-shadow: 2px 2px 0 #0A0A0A !important;
    transform: translate(-1px, -1px);
}

@media (max-width: 860px) {
    .card {
        grid-template-columns: 1fr !important;
    }
    .login-left-pane {
        border-right: none !important;
        border-bottom: 2px solid #0A0A0A !important;
        padding: 1.75rem 1.5rem !important;
    }
}
</style>
@endsection
