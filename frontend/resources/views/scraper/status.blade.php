@extends('layouts.app')

@section('title', '§ 04.0 Monitor Scraper Telemetri')

@section('breadcrumb')
<span style="color:#0A0A0A;">§ 04.0 TELEMETRI SCRAPER</span>
@endsection

@section('content')
<script>
function scraperTelemetry(config) {
    return {
        isOnline: config.initialOnline,
        sessionData: config.initialSessionData || null,
        loginEnv: config.loginEnv || null,
        checkUrl: config.checkUrl,
        isChecking: false,
        pollTimer: null,

        get xValid() {
            return !!(this.sessionData && this.sessionData.x && this.sessionData.x.is_valid === true);
        },

        get xMessage() {
            if (!this.isOnline) {
                return 'Subsistem AI offline, telemetri sesi tidak dapat diambil.';
            }
            return (this.sessionData && this.sessionData.x && this.sessionData.x.message)
                ? this.sessionData.x.message
                : 'Sesi belum dikonfigurasi atau cookie kadaluarsa.';
        },

        get threadsValid() {
            return !!(this.sessionData && this.sessionData.threads && this.sessionData.threads.is_valid === true);
        },

        get threadsMessage() {
            if (!this.isOnline) {
                return 'Subsistem AI offline, telemetri sesi tidak dapat diambil.';
            }
            return (this.sessionData && this.sessionData.threads && this.sessionData.threads.message)
                ? this.sessionData.threads.message
                : 'Sesi belum dikonfigurasi atau cookie kadaluarsa.';
        },

        // ── Lingkungan GUI login (sumber: backend login_environment) ──────────
        get guiMode() {
            return (this.loginEnv && this.loginEnv.gui_mode) ? this.loginEnv.gui_mode : null;
        },

        // URL noVNC dari backend; host ditukar bila portal diakses via IP LAN/host lain.
        get novncUrl() {
            const raw = (this.loginEnv && this.loginEnv.novnc_url) ? this.loginEnv.novnc_url : null;
            if (!raw) return null;

            const host = window.location.hostname;
            if (host && host !== 'localhost' && host !== '127.0.0.1') {
                return raw.replace(/\/\/(localhost|127\.0\.0\.1)(?=[:/])/, '//' + host);
            }
            return raw;
        },

        /**
         * Dipanggil pada event klik tombol Re-Authenticate (user gesture) sehingga
         * jendela noVNC boleh dibuka tanpa diblokir popup blocker.
         * Form tetap di-submit secara native agar redirect & flash Laravel berjalan.
         * Pada runtime lokal (guiMode 'native') jendela Chrome/Edge dibuka oleh server,
         * jadi tidak ada aksi tambahan yang diperlukan di sisi browser.
         */
        requestLogin() {
            if (this.guiMode === 'novnc' && this.novncUrl) {
                window.open(this.novncUrl, '_blank', 'noopener');
            }
        },

        async checkStatus() {
            if (this.isChecking) return;
            this.isChecking = true;
            try {
                const res = await fetch(this.checkUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.isOnline = !!data.isServerOnline;
                    this.sessionData = data.sessionData || null;
                    this.loginEnv = data.loginEnvironment || null;
                } else {
                    this.isOnline = false;
                    this.sessionData = null;
                    this.loginEnv = null;
                }
            } catch (err) {
                this.isOnline = false;
                this.sessionData = null;
                this.loginEnv = null;
            } finally {
                this.isChecking = false;
                window.__SCRAPER_POLLING_ACTIVE__ = true;
                window.dispatchEvent(new CustomEvent('fastapi-status-changed', {
                    detail: { isOnline: this.isOnline }
                }));
            }
        },

        init() {
            window.__SCRAPER_POLLING_ACTIVE__ = true;

            // Polling interval setiap 3 detik
            this.pollTimer = setInterval(() => {
                this.checkStatus();
            }, 3000);

            // Bersihkan timer saat halaman ditutup atau berpindah
            window.addEventListener('beforeunload', () => {
                window.__SCRAPER_POLLING_ACTIVE__ = false;
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }
            });
        }
    };
}
</script>

<div x-data="scraperTelemetry(@js([
    'initialOnline'      => (bool)$isServerOnline,
    'initialSessionData' => $sessionData,
    'loginEnv'           => $loginEnvironment,
    'checkUrl'           => route('scraper.status'),
]))" style="max-width:880px;margin:0 auto;">

    {{-- Monograph Section Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1.25rem;margin-bottom:2rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                <span class="badge badge-black">SEKSI § 04.0</span>
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">DIAGNOSTIK INFRASTRUKTUR AI</span>
            </div>
            <h1 style="font-size:2.25rem;font-weight:900;letter-spacing:-0.035em;color:#0A0A0A;margin:0;line-height:1.1;">
                TELEMETRI MESIN & SESI
            </h1>
            <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
                Status operasional server inferensi FastAPI dan sesi persistent Playwright browser automation.
            </p>
        </div>

        {{-- Live Telemetry Auto-Sync Status Indicator (Menggantikan tombol refresh manual) --}}
        <div style="display:flex;align-items:center;gap:0.625rem;font-family:var(--font-mono);font-size:0.75rem;background:var(--color-surface-2);border:1px solid #0A0A0A;padding:0.4rem 0.75rem;box-shadow:2px 2px 0 #0A0A0A;">
            <span class="telemetry-dot pulse"
                  :style="isOnline ? 'background-color:var(--color-teal);' : 'background-color:var(--color-danger);'"
                  style="background-color: {{ $isServerOnline ? 'var(--color-teal)' : 'var(--color-danger)' }};"></span>
            <span style="font-weight:700;letter-spacing:0.04em;"
                  x-text="isOnline ? 'AUTO-SYNC TELEMETRI' : 'MENUNGGU BACKEND'">
                {{ $isServerOnline ? 'AUTO-SYNC TELEMETRI' : 'MENUNGGU BACKEND' }}
            </span>
        </div>
    </div>

    {{-- ── 1. FastAPI AI Server Telemetry ── --}}
    <div class="card"
         :style="isOnline ? 'padding:1.5rem;margin-bottom:1.5rem;border-left:6px solid var(--color-teal);transition:border-color 0.4s ease;' : 'padding:1.5rem;margin-bottom:1.5rem;border-left:6px solid var(--color-danger);transition:border-color 0.4s ease;'"
         style="padding:1.5rem;margin-bottom:1.5rem;border-left:6px solid {{ $isServerOnline ? 'var(--color-teal)' : 'var(--color-danger)' }};">
        
        {{-- Card Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;border-bottom:1px solid #0A0A0A;padding-bottom:1rem;margin-bottom:1.25rem;">
            <div>
                <div style="display:flex;align-items:center;gap:0.625rem;margin-bottom:0.35rem;">
                    <span class="badge badge-black">ENGINE AI</span>
                    <span class="badge badge-mono">NLP & CRAWLER SUBSYSTEM</span>
                    <h2 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0;">FastAPI Python Subsystem</h2>
                </div>
                <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0;">
                    Subsistem inferensi pemodelan bahasa alami (NLP) dan orkestrasi browser automation.
                </p>
            </div>

            <div style="display:flex;align-items:center;gap:0.625rem;">
                <span class="badge badge-mono" style="font-size:0.6875rem;">SECURE IPC CHANNEL</span>
                <span class="badge"
                      :class="isOnline ? 'badge-safe' : 'badge-hate'"
                      x-text="isOnline ? '● ONLINE (200 OK)' : '○ OFFLINE / STANDBY'">
                    {{ $isServerOnline ? '● ONLINE (200 OK)' : '○ OFFLINE / STANDBY' }}
                </span>
            </div>
        </div>

        {{-- Dynamic Status & Diagnostic Banner --}}
        <div>
            {{-- Online State: Model Loaded & Ready --}}
            <div x-show="isOnline" @if(!$isServerOnline) style="display:none;" @endif>
                <div style="background:var(--color-surface-2);border:1px solid #0A0A0A;border-left:4px solid var(--color-teal);padding:0.75rem 1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <span class="telemetry-dot pulse" style="background-color:var(--color-teal);width:10px;height:10px;"></span>
                        <div>
                            <span style="font-family:var(--font-mono);font-size:0.8125rem;font-weight:800;color:#0A0A0A;display:block;">
                                SUBSISTEM OPERASIONAL LENGKAP & MEMORI MODEL TERMUAT
                            </span>
                            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">
                                Engine inferensi siap melayani request klasifikasi teks dan autentikasi scraping otomatis.
                            </span>
                        </div>
                    </div>
                    <span class="badge badge-safe" style="font-size:0.6875rem;letter-spacing:0.04em;">INFERENCE READY</span>
                </div>
            </div>

            {{-- Offline State: Graceful Standby & Auto-Reconnect --}}
            <div x-show="!isOnline" @if($isServerOnline) style="display:none;" @endif>
                <div style="background:var(--color-surface-2);border:1px solid #0A0A0A;border-left:4px solid var(--color-danger);padding:0.75rem 1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <span class="telemetry-dot pulse" style="background-color:var(--color-danger);width:10px;height:10px;"></span>
                        <div>
                            <span style="font-family:var(--font-mono);font-size:0.8125rem;font-weight:800;color:var(--color-danger);display:block;">
                                SUBSISTEM INFERENSI SEDANG STANDBY / TIDAK TERHUBUNG
                            </span>
                            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">
                                Layanan inferensi sedang proses inisialisasi atau belum diaktifkan. Sistem melakukan sinkronisasi otomatis.
                            </span>
                        </div>
                    </div>
                    <span class="badge badge-hate" style="font-size:0.6875rem;letter-spacing:0.04em;">AUTO-SYNCING</span>
                </div>
            </div>
        </div>

    </div>

    {{-- ── 2. Grid Sesi Media Sosial (X & Threads) ── --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(360px, 1fr));gap:1.5rem;margin-bottom:1.75rem;">

        {{-- Twitter X --}}
        @php
            $xStatus = $sessionData['x'] ?? null;
            $xValid  = $xStatus['is_valid'] ?? false;
        @endphp
        <div class="card" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between;gap:1.25rem;">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #0A0A0A;padding-bottom:0.75rem;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span class="badge badge-black" style="font-size:0.75rem;">𝕏</span>
                        <span style="font-family:var(--font-mono);font-weight:800;font-size:0.9375rem;color:#0A0A0A;">TWITTER SCRAPER</span>
                    </div>

                    <span class="badge"
                          :class="!isOnline ? 'badge-mono' : (xValid ? 'badge-safe' : 'badge-hate')"
                          x-text="!isOnline ? 'TIDAK DIKETAHUI' : (xValid ? 'SESI AKTIF' : 'PERLU LOGIN')">
                        @if($isServerOnline)
                            {{ $xValid ? 'SESI AKTIF' : 'PERLU LOGIN' }}
                        @else
                            TIDAK DIKETAHUI
                        @endif
                    </span>
                </div>

                <div class="card-flat" style="padding:0.875rem;margin-bottom:0.75rem;font-family:var(--font-mono);font-size:0.75rem;">
                    <span style="color:var(--color-text-muted);display:block;margin-bottom:0.25rem;">STATUS SESI PLAYWRIGHT:</span>
                    <p style="margin:0;color:#0A0A0A;line-height:1.5;font-weight:600;" x-text="xMessage">
                        {{ $xStatus['message'] ?? ($isServerOnline ? 'Sesi belum dikonfigurasi atau cookie kadaluarsa.' : 'Server AI offline, telemetri tidak dapat diambil.') }}
                    </p>
                </div>
            </div>

            @can('manage-auth-sessions')
            <form method="POST" action="{{ route('scraper.login-trigger', 'x') }}">
                @csrf
                <button type="submit" class="btn btn-outline" style="width:100%;height:40px;" @click="requestLogin()" :disabled="!isOnline" {{ !$isServerOnline ? 'disabled' : '' }}>
                    <span>⟳ RE-AUTHENTICATE 𝕏 TWITTER</span>
                </button>
            </form>
            @endcan
        </div>

        {{-- Threads --}}
        @php
            $threadsStatus = $sessionData['threads'] ?? null;
            $threadsValid  = $threadsStatus['is_valid'] ?? false;
        @endphp
        <div class="card" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between;gap:1.25rem;">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #0A0A0A;padding-bottom:0.75rem;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span class="badge badge-mono" style="font-size:0.75rem;">⊙</span>
                        <span style="font-family:var(--font-mono);font-weight:800;font-size:0.9375rem;color:#0A0A0A;">THREADS SCRAPER</span>
                    </div>

                    <span class="badge"
                          :class="!isOnline ? 'badge-mono' : (threadsValid ? 'badge-safe' : 'badge-hate')"
                          x-text="!isOnline ? 'TIDAK DIKETAHUI' : (threadsValid ? 'SESI AKTIF' : 'PERLU LOGIN')">
                        @if($isServerOnline)
                            {{ $threadsValid ? 'SESI AKTIF' : 'PERLU LOGIN' }}
                        @else
                            TIDAK DIKETAHUI
                        @endif
                    </span>
                </div>

                <div class="card-flat" style="padding:0.875rem;margin-bottom:0.75rem;font-family:var(--font-mono);font-size:0.75rem;">
                    <span style="color:var(--color-text-muted);display:block;margin-bottom:0.25rem;">STATUS SESI PLAYWRIGHT:</span>
                    <p style="margin:0;color:#0A0A0A;line-height:1.5;font-weight:600;" x-text="threadsMessage">
                        {{ $threadsStatus['message'] ?? ($isServerOnline ? 'Sesi belum dikonfigurasi atau cookie kadaluarsa.' : 'Server AI offline, telemetri tidak dapat diambil.') }}
                    </p>
                </div>
            </div>

            @can('manage-auth-sessions')
            <form method="POST" action="{{ route('scraper.login-trigger', 'threads') }}">
                @csrf
                <button type="submit" class="btn btn-outline" style="width:100%;height:40px;" @click="requestLogin()" :disabled="!isOnline" {{ !$isServerOnline ? 'disabled' : '' }}>
                    <span>⟳ RE-AUTHENTICATE META THREADS</span>
                </button>
            </form>
            @endcan
        </div>

    </div>
</div>

@endsection
