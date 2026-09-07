@extends('layouts.app')

@section('title', '§ 04.0 Monitor Scraper Telemetri')

@section('breadcrumb')
<span style="color:#0A0A0A;">§ 04.0 TELEMETRI SCRAPER</span>
@endsection

@section('content')

<div style="max-width:880px;margin:0 auto;">

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

        <a href="{{ route('scraper.status') }}" class="btn btn-outline btn-sm">
            <span>⟳ REFRESH STATUS</span>
        </a>
    </div>

    {{-- ── 1. FastAPI AI Server Telemetry ── --}}
    <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;border-left:6px solid {{ $isServerOnline ? 'var(--color-teal)' : 'var(--color-danger)' }};">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
                <div style="display:flex;align-items:center;gap:0.625rem;margin-bottom:0.35rem;">
                    <span class="badge badge-black">ENGINE AI</span>
                    <h2 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0;">FastAPI Python Backend</h2>
                    @if($isServerOnline)
                        <span class="badge badge-safe">ONLINE (200 OK)</span>
                    @else
                        <span class="badge badge-hate">OFFLINE / TIDAK AKTIF</span>
                    @endif
                </div>
                <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0;">
                    GATEWAY: <code style="background:var(--color-surface-2);border:1px solid #0A0A0A;padding:2px 6px;color:#0A0A0A;font-weight:700;">{{ config('services.fastapi.url', 'http://127.0.0.1:8080/api/v1') }}</code>
                </p>
            </div>

            <div>
                @if($isServerOnline)
                <span class="badge badge-safe" style="font-size:0.75rem;">
                    <span class="telemetry-dot"></span>
                    <span>MODEL INDOBERT TERMUAT DI MEMORI</span>
                </span>
                @else
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-danger-ink);background:var(--color-danger);padding:4px 8px;border:1px solid #0A0A0A;display:block;">
                    Jalankan: uvicorn main_api:app --reload --port 8080
                </span>
                @endif
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

                    @if($isServerOnline)
                        @if($xValid)
                            <span class="badge badge-safe">SESI AKTIF</span>
                        @else
                            <span class="badge badge-hate">PERLU LOGIN</span>
                        @endif
                    @else
                        <span class="badge badge-mono">TIDAK DIKETAHUI</span>
                    @endif
                </div>

                <div class="card-flat" style="padding:0.875rem;margin-bottom:0.75rem;font-family:var(--font-mono);font-size:0.75rem;">
                    <span style="color:var(--color-text-muted);display:block;margin-bottom:0.25rem;">STATUS SESI PLAYWRIGHT:</span>
                    <p style="margin:0;color:#0A0A0A;line-height:1.5;font-weight:600;">
                        {{ $xStatus['message'] ?? ($isServerOnline ? 'Sesi belum dikonfigurasi atau cookie kadaluarsa.' : 'Server AI offline, telemetri tidak dapat diambil.') }}
                    </p>
                </div>
            </div>

            @can('manage-auth-sessions')
            <form method="POST" action="{{ route('scraper.login-trigger', 'x') }}">
                @csrf
                <button type="submit" class="btn btn-outline" style="width:100%;height:40px;" {{ !$isServerOnline ? 'disabled' : '' }}>
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

                    @if($isServerOnline)
                        @if($threadsValid)
                            <span class="badge badge-safe">SESI AKTIF</span>
                        @else
                            <span class="badge badge-hate">PERLU LOGIN</span>
                        @endif
                    @else
                        <span class="badge badge-mono">TIDAK DIKETAHUI</span>
                    @endif
                </div>

                <div class="card-flat" style="padding:0.875rem;margin-bottom:0.75rem;font-family:var(--font-mono);font-size:0.75rem;">
                    <span style="color:var(--color-text-muted);display:block;margin-bottom:0.25rem;">STATUS SESI PLAYWRIGHT:</span>
                    <p style="margin:0;color:#0A0A0A;line-height:1.5;font-weight:600;">
                        {{ $threadsStatus['message'] ?? ($isServerOnline ? 'Sesi belum dikonfigurasi atau cookie kadaluarsa.' : 'Server AI offline, telemetri tidak dapat diambil.') }}
                    </p>
                </div>
            </div>

            @can('manage-auth-sessions')
            <form method="POST" action="{{ route('scraper.login-trigger', 'threads') }}">
                @csrf
                <button type="submit" class="btn btn-outline" style="width:100%;height:40px;" {{ !$isServerOnline ? 'disabled' : '' }}>
                    <span>⟳ RE-AUTHENTICATE META THREADS</span>
                </button>
            </form>
            @endcan
        </div>

    </div>

    {{-- ── 3. Catatan Teknis Lab ── --}}
    <div class="card-flat" style="padding:1.25rem 1.5rem;">
        <span class="stat-block-label" style="display:block;margin-bottom:0.5rem;">PANDUAN OPERASIONAL PERSISTENT SESSION</span>
        <ul style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);line-height:1.75;padding-left:1.25rem;margin:0;">
            <li>Sistem menggunakan <strong>Playwright Persistent Browser Context</strong> sehingga kredensial disimpan lokal dalam direktori aman dan tidak perlu login ulang pada setiap batch crawling.</li>
            <li>Apabila platform mendeteksi checkpoint berkala, gunakan tombol <strong>Re-Authenticate</strong> untuk membuka browser interaktif dan memperbarui session cookie.</li>
            <li>Crawler di latar belakang secara otomatis mengabaikan platform yang sesinya non-aktif untuk mencegah pemblokiran IP.</li>
        </ul>
    </div>

</div>

@endsection
