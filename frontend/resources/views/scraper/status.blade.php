@extends('layouts.app')

@section('title', '§ 04.0 Scraper Telemetry')

@section('breadcrumb')
<span style="color:#0A0A0A;">§ 04.0 SCRAPER TELEMETRY</span>
@endsection

@section('content')
{{-- JSON Island: data server awal untuk halaman telemetri scraper --}}
<script id="scraper-telemetry-data" type="application/json">
    {!! json_encode([
        'initialOnline'      => (bool) $isServerOnline,
        'initialSessionData' => $sessionData,
        'loginEnv'           => $loginEnvironment,
        'checkUrl'           => route('scraper.status'),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
</script>

<div x-data="scraperTelemetry()" style="max-width:880px;margin:0 auto;">

    {{-- Monograph Section Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1.25rem;margin-bottom:2rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                <span class="badge badge-black">SECTION § 04.0</span>
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">AI INFRASTRUCTURE DIAGNOSTICS</span>
            </div>
            <h1 style="font-size:2.25rem;font-weight:900;letter-spacing:-0.035em;color:#0A0A0A;margin:0;line-height:1.1;">
                ENGINE & SESSION TELEMETRY
            </h1>
            <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
                Operational status for the FastAPI inference server and persistent Playwright browser sessions.
            </p>
        </div>

        {{-- Live telemetry auto-sync status indicator. --}}
        <div style="display:flex;align-items:center;gap:0.625rem;font-family:var(--font-mono);font-size:0.75rem;background:var(--color-surface-2);border:1px solid #0A0A0A;padding:0.4rem 0.75rem;box-shadow:2px 2px 0 #0A0A0A;">
            <span class="telemetry-dot pulse"
                  :style="isOnline ? 'background-color:var(--color-teal);' : 'background-color:var(--color-danger);'"
                  style="background-color: {{ $isServerOnline ? 'var(--color-teal)' : 'var(--color-danger)' }};"></span>
            <span style="font-weight:700;letter-spacing:0.04em;"
                                    x-text="isOnline ? 'TELEMETRY AUTO-SYNC' : 'WAITING FOR BACKEND'">
                                {{ $isServerOnline ? 'TELEMETRY AUTO-SYNC' : 'WAITING FOR BACKEND' }}
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
                    Natural language inference (NLP) and browser automation orchestration subsystem.
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
                                SUBSYSTEM OPERATIONAL & MODEL LOADED
                            </span>
                            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">
                                Inference engine ready for text classification and automated scraper authentication.
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
                                INFERENCE SUBSYSTEM ON STANDBY / DISCONNECTED
                            </span>
                            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">
                                Inference service is initializing or inactive. The system will retry automatically.
                            </span>
                        </div>
                    </div>
                    <span class="badge badge-hate" style="font-size:0.6875rem;letter-spacing:0.04em;">AUTO-SYNCING</span>
                </div>
            </div>
        </div>

    </div>

    {{-- ── 2. Grid Sesi Media Sosial (X & Threads) ── --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(360px, 100%), 1fr));gap:1.5rem;margin-bottom:1.75rem;">

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
                          x-text="!isOnline ? 'UNKNOWN' : (xValid ? 'SESSION ACTIVE' : 'LOGIN REQUIRED')">
                        @if($isServerOnline)
                            {{ $xValid ? 'SESSION ACTIVE' : 'LOGIN REQUIRED' }}
                        @else
                            UNKNOWN
                        @endif
                    </span>
                </div>

                <div class="card-flat" style="padding:0.875rem;margin-bottom:0.75rem;font-family:var(--font-mono);font-size:0.75rem;">
                    <span style="color:var(--color-text-muted);display:block;margin-bottom:0.25rem;">PLAYWRIGHT SESSION STATUS:</span>
                    <p style="margin:0;color:#0A0A0A;line-height:1.5;font-weight:600;" x-text="xMessage">
                        {{ $xStatus['message'] ?? ($isServerOnline ? 'Session is not configured or its cookies have expired.' : 'AI server offline; telemetry is unavailable.') }}
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
                          x-text="!isOnline ? 'UNKNOWN' : (threadsValid ? 'SESSION ACTIVE' : 'LOGIN REQUIRED')">
                        @if($isServerOnline)
                            {{ $threadsValid ? 'SESSION ACTIVE' : 'LOGIN REQUIRED' }}
                        @else
                            UNKNOWN
                        @endif
                    </span>
                </div>

                <div class="card-flat" style="padding:0.875rem;margin-bottom:0.75rem;font-family:var(--font-mono);font-size:0.75rem;">
                    <span style="color:var(--color-text-muted);display:block;margin-bottom:0.25rem;">PLAYWRIGHT SESSION STATUS:</span>
                    <p style="margin:0;color:#0A0A0A;line-height:1.5;font-weight:600;" x-text="threadsMessage">
                        {{ $threadsStatus['message'] ?? ($isServerOnline ? 'Session is not configured or its cookies have expired.' : 'AI server offline; telemetry is unavailable.') }}
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
