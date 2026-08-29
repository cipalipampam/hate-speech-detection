@extends('layouts.app')

@section('title', 'Monitor Scraper & Sesi Akun')

@section('breadcrumb')
<div style="display:flex;align-items:center;gap:0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">Monitor Scraper</span>
</div>
@endsection

@section('content')

<div style="max-width:860px;margin:0 auto;">

    {{-- Page Header --}}
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="page-title">Monitor Scraper & Sesi</h1>
            <p class="page-subtitle">Pemantauan status koneksi server AI dan sesi persistent browser Playwright untuk media sosial.</p>
        </div>
        <a href="{{ route('scraper.status') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:0.375rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            Refresh Status
        </a>
    </div>

    {{-- ── 1. Status Server AI (FastAPI) ── --}}
    <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;border-left:4px solid {{ $isServerOnline ? 'var(--color-teal)' : 'var(--color-warning)' }};">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div style="display:flex;align-items:center;gap:1rem;">
                <div style="width:44px;height:44px;border-radius:0.75rem;background:{{ $isServerOnline ? 'var(--color-teal-bg)' : 'var(--color-warning-bg)' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    @if($isServerOnline)
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-teal)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    @else
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-warning)" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    @endif
                </div>
                <div>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                        <p style="font-size:1.0625rem;font-weight:800;color:var(--color-navy);">FastAPI AI Backend Engine</p>
                        @if($isServerOnline)
                            <span class="badge badge-safe">Online</span>
                        @else
                            <span class="badge badge-warning">Offline / Belum Aktif</span>
                        @endif
                    </div>
                    <p style="font-size:0.8125rem;color:var(--color-text-muted);">
                        Endpoint: <code style="background:var(--color-surface-2);padding:2px 6px;border-radius:4px;font-size:0.8125rem;color:var(--color-navy);font-family:monospace;">{{ config('services.fastapi.url', 'http://127.0.0.1:8080/api/v1') }}</code>
                    </p>
                </div>
            </div>
            <div>
                @if(!$isServerOnline)
                <span style="font-size:0.75rem;color:var(--color-text-muted);display:block;text-align:right;">
                    Jalankan: <code style="background:#FFF;border:1px solid var(--color-border);padding:2px 6px;border-radius:4px;">uvicorn main_api:app --reload --port 8080</code>
                </span>
                @else
                <span style="font-size:0.75rem;color:var(--color-teal);font-weight:700;display:flex;align-items:center;gap:4px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:var(--color-teal);display:inline-block;"></span>
                    Model IndoBERT Siap
                </span>
                @endif
            </div>
        </div>
    </div>

    {{-- ── 2. Grid Status Sesi Media Sosial (X & Threads) ── --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(360px, 1fr));gap:1.25rem;margin-bottom:1.5rem;">

        {{-- Card Sesi Twitter (X) --}}
        @php
            $xStatus = $sessionData['x'] ?? null;
            $xValid  = $xStatus['is_valid'] ?? false;
        @endphp
        <div class="card" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between;gap:1.25rem;">
            <div>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="width:42px;height:42px;border-radius:0.75rem;background:#0F1419;display:flex;align-items:center;justify-content:center;color:#FFF;flex-shrink:0;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </div>
                        <div>
                            <p style="font-size:1rem;font-weight:800;color:var(--color-navy);">Twitter (𝕏) Scraper</p>
                            <p style="font-size:0.75rem;color:var(--color-text-muted);">Playwright Persistent Context</p>
                        </div>
                    </div>

                    @if($isServerOnline)
                        @if($xValid)
                            <span class="badge badge-safe">Sesi Aktif</span>
                        @else
                            <span class="badge badge-hate">Perlu Login</span>
                        @endif
                    @else
                        <span class="badge" style="background:var(--color-surface-2);color:var(--color-text-muted);">Tidak Diketahui</span>
                    @endif
                </div>

                <div style="background:var(--color-surface-2);border-radius:0.75rem;padding:0.875rem;margin-bottom:0.75rem;">
                    <p style="font-size:0.75rem;font-weight:700;color:var(--color-text-muted);margin-bottom:0.25rem;">STATUS SESI BROWSER:</p>
                    <p style="font-size:0.8125rem;color:var(--color-text-body);line-height:1.5;">
                        {{ $xStatus['message'] ?? ($isServerOnline ? 'Sesi belum dikonfigurasi atau kedaluwarsa.' : 'Server AI offline, status tidak dapat diverifikasi.') }}
                    </p>
                </div>
            </div>

            @can('manage-auth-sessions')
            <form method="POST" action="{{ route('scraper.login-trigger', 'x') }}">
                @csrf
                <button type="submit" class="btn btn-outline" style="width:100%;height:40px;justify-content:center;" {{ !$isServerOnline ? 'disabled' : '' }}>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    <span>Login Ulang Akun 𝕏</span>
                </button>
            </form>
            @endcan
        </div>

        {{-- Card Sesi Threads --}}
        @php
            $threadsStatus = $sessionData['threads'] ?? null;
            $threadsValid  = $threadsStatus['is_valid'] ?? false;
        @endphp
        <div class="card" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between;gap:1.25rem;">
            <div>
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="width:42px;height:42px;border-radius:0.75rem;background:#1E3A4C;display:flex;align-items:center;justify-content:center;color:#FFF;flex-shrink:0;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>
                            </svg>
                        </div>
                        <div>
                            <p style="font-size:1rem;font-weight:800;color:var(--color-navy);">Threads Scraper</p>
                            <p style="font-size:0.75rem;color:var(--color-text-muted);">Playwright Persistent Context</p>
                        </div>
                    </div>

                    @if($isServerOnline)
                        @if($threadsValid)
                            <span class="badge badge-safe">Sesi Aktif</span>
                        @else
                            <span class="badge badge-hate">Perlu Login</span>
                        @endif
                    @else
                        <span class="badge" style="background:var(--color-surface-2);color:var(--color-text-muted);">Tidak Diketahui</span>
                    @endif
                </div>

                <div style="background:var(--color-surface-2);border-radius:0.75rem;padding:0.875rem;margin-bottom:0.75rem;">
                    <p style="font-size:0.75rem;font-weight:700;color:var(--color-text-muted);margin-bottom:0.25rem;">STATUS SESI BROWSER:</p>
                    <p style="font-size:0.8125rem;color:var(--color-text-body);line-height:1.5;">
                        {{ $threadsStatus['message'] ?? ($isServerOnline ? 'Sesi belum dikonfigurasi atau kedaluwarsa.' : 'Server AI offline, status tidak dapat diverifikasi.') }}
                    </p>
                </div>
            </div>

            @can('manage-auth-sessions')
            <form method="POST" action="{{ route('scraper.login-trigger', 'threads') }}">
                @csrf
                <button type="submit" class="btn btn-outline" style="width:100%;height:40px;justify-content:center;" {{ !$isServerOnline ? 'disabled' : '' }}>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    <span>Login Ulang Akun Threads</span>
                </button>
            </form>
            @endcan
        </div>

    </div>

    {{-- ── 3. Informasi Teknis & Best Practice ── --}}
    <div class="card-flat" style="padding:1.5rem;">
        <p style="font-size:0.875rem;font-weight:700;color:var(--color-navy);margin-bottom:0.75rem;">💡 PANDUAN PENGELOLAAN SESI SCRAPER</p>
        <ul style="font-size:0.8125rem;color:var(--color-text-muted);line-height:1.8;padding-left:1.25rem;">
            <li>Sistem menggunakan <strong>Persistent Context Playwright</strong> sehingga cookie login disimpan permanen di direktori server dan tidak perlu login setiap kali scraping dijalankan.</li>
            <li>Jika platform media sosial memutus sesi (misal karena checkpoint berkala), klik tombol <strong>"Login Ulang"</strong> di atas untuk membuka browser interaktif dan memperbarui session cookie.</li>
            <li>Proses scraping di latar belakang akan otomatis menggunakan akun yang status sesinya aktif untuk menghindari rate limit.</li>
        </ul>
    </div>

</div>

@endsection
