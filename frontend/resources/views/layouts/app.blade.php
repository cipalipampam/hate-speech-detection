<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Sistem Deteksi Ujaran Kebencian Multi-Platform menggunakan Hierarchical IndoBERT')">
    <title>@yield('title', 'Portal Analisis') — HateSense ID Lab</title>

    {{-- Google Fonts: Plus Jakarta Sans & JetBrains Mono --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="antialiased" x-data="{ cmdOpen: false, mobileMenu: false }"
      @keydown.window.ctrl.k.prevent="cmdOpen = !cmdOpen"
      @keydown.window.meta.k.prevent="cmdOpen = !cmdOpen"
      @keydown.window.escape="cmdOpen = false">

    {{-- ═══════════════════════════════════════════════════════════
         SWISS MONOGRAPH TOP MASTHEAD
    ════════════════════════════════════════════════════════════ --}}
    <header class="masthead">
        {{-- Brand / Masthead Title --}}
        <div style="display:flex;align-items:center;height:100%;flex-shrink:0;">
            <a href="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:0.625rem;text-decoration:none;padding-right:1rem;border-right:1px solid var(--color-border);height:100%;flex-shrink:0;">
                <div style="background:#0A0A0A;color:#FFFFFF;font-family:var(--font-mono);font-weight:900;font-size:0.875rem;padding:0.25rem 0.45rem;line-height:1;border:1px solid #0A0A0A;flex-shrink:0;">
                    HS
                </div>
                <div style="white-space:nowrap;">
                    <span style="font-weight:900;font-size:0.875rem;color:#0A0A0A;letter-spacing:-0.03em;display:block;line-height:1.1;">
                        HATESENSE <span style="color:var(--color-primary);">ID</span>
                    </span>
                    <span style="font-family:var(--font-mono);font-size:0.5625rem;color:var(--color-text-muted);letter-spacing:0.04em;text-transform:uppercase;">
                        RESEARCH LAB · V2.4
                    </span>
                </div>
            </a>

            {{-- Numbered Monograph Navigation (Desktop >= 1200px) --}}
            <nav class="masthead-nav">
                <a href="{{ route('dashboard') }}" class="masthead-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    01 OVERVIEW
                </a>

                @can('view-dashboard')
                <a href="{{ route('analyses.index') }}" class="masthead-link {{ (request()->routeIs('analyses.index') || request()->routeIs('analyses.show')) ? 'active' : '' }}">
                    02 RIWAYAT
                </a>

                @can('run-analysis')
                <a href="{{ route('analyses.create') }}" class="masthead-link {{ request()->routeIs('analyses.create') ? 'active' : '' }}" style="color:var(--color-primary);">
                    + ANALISIS
                </a>
                @endcan
                @endcan

                @can('test-single-prediction')
                <a href="{{ route('predict.index') }}" class="masthead-link {{ request()->routeIs('predict.*') ? 'active' : '' }}">
                    03 SANDBOX
                </a>
                <a href="{{ route('scraper.status') }}" class="masthead-link {{ request()->routeIs('scraper.*') ? 'active' : '' }}">
                    04 SCRAPER
                </a>
                @endcan

                @can('manage-users')
                <a href="{{ route('admin.users.index') }}" class="masthead-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    05 PENGGUNA
                </a>
                @endcan
            </nav>
        </div>

        {{-- Right Side Telemetry & Controls --}}
        <div style="display:flex;align-items:center;gap:0.5rem;flex-shrink:0;">
            {{-- Quick Command Trigger --}}
            <button @click="cmdOpen = true" class="btn btn-outline btn-sm masthead-cmd-btn" style="gap:0.35rem;height:32px;padding:0 0.5rem;" title="Buka Command Palette (Ctrl + K)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span style="font-size:0.75rem;">CMD</span>
                <kbd style="background:var(--color-surface-2);border:1px solid #0A0A0A;padding:1px 4px;font-size:0.625rem;">⌘K</kbd>
            </button>

            {{-- Telemetry Server Pill --}}
            <div class="badge badge-mono" style="padding:0.25rem 0.5rem;font-size:0.6875rem;white-space:nowrap;" title="FastAPI Engine Status: 200 OK">
                <span class="telemetry-dot pulse"></span>
                <span class="telemetry-pill-text">FASTAPI · 200 OK</span>
            </div>

            {{-- User Account Dropdown --}}
            <div x-data="{ userMenu: false }" style="position:relative;">
                <button @click="userMenu = !userMenu" class="btn btn-outline btn-sm" style="height:32px;padding:0 0.5rem;gap:0.35rem;">
                    <span style="font-weight:800;color:var(--color-primary);">[</span>
                    <span style="max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.75rem;">{{ auth()->user()->name ?? 'USER' }}</span>
                    <span style="font-weight:800;color:var(--color-primary);">]</span>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </button>

                <div x-show="userMenu" x-cloak @click.outside="userMenu = false"
                     style="position:absolute;top:calc(100% + 4px);right:0;width:220px;background:#FFFFFF;border:1px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;z-index:100;">
                    <div style="padding:0.625rem 0.75rem;background:var(--color-surface-2);border-bottom:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.6875rem;">
                        <span style="color:var(--color-text-muted);display:block;">AKUN PENELITI:</span>
                        <span style="font-weight:700;color:#0A0A0A;">{{ auth()->user()->email ?? '' }}</span>
                        <span class="badge badge-mono" style="margin-top:0.25rem;font-size:0.625rem;">
                            ROLE: {{ strtoupper(auth()->user()->getRoleNames()->first() ?? 'VIEWER') }}
                        </span>
                    </div>

                    <a href="{{ route('profile.edit') }}"
                       style="display:flex;align-items:center;gap:0.5rem;padding:0.625rem 0.75rem;font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:#0A0A0A;text-decoration:none;border-bottom:1px solid var(--color-border-subtle);"
                       onmouseover="this.style.background='var(--color-surface-2)'" onmouseout="this.style.background=''">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        PENGATURAN PROFIL
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                style="width:100%;text-align:left;display:flex;align-items:center;gap:0.5rem;padding:0.625rem 0.75rem;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:var(--color-crimson);background:none;border:none;cursor:pointer;"
                                onmouseover="this.style.background='var(--color-crimson-bg)'" onmouseout="this.style.background=''">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            KELUAR (LOGOUT)
                        </button>
                    </form>
                </div>
            </div>

            {{-- Mobile Menu Hamburger --}}
            <button @click="mobileMenu = !mobileMenu" class="btn btn-outline btn-sm masthead-hamburger" style="height:32px;padding:0 0.5rem;" title="Buka Menu Navigasi">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </header>

    {{-- Mobile / Collapsed Menu Drawer --}}
    <div x-show="mobileMenu" x-cloak @click.outside="mobileMenu = false"
         style="position:fixed;top:56px;left:0;right:0;background:#FFFFFF;border-bottom:2px solid #0A0A0A;box-shadow:0 8px 0 rgba(0,0,0,0.15);z-index:49;padding:1rem 1.25rem;">
        <div style="border-bottom:1px solid var(--color-border-subtle);padding-bottom:0.5rem;margin-bottom:0.75rem;display:flex;justify-content:space-between;align-items:center;">
            <span class="stat-block-label">§ INDEX NAVIGASI SISTEM</span>
            <button @click="mobileMenu = false" style="background:none;border:none;font-family:var(--font-mono);font-size:1rem;font-weight:900;cursor:pointer;">✕</button>
        </div>
        <nav style="display:flex;flex-direction:column;gap:0.375rem;">
            <a href="{{ route('dashboard') }}" class="btn {{ request()->routeIs('dashboard') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">§ 01.0 OVERVIEW DASHBOARD</a>
            <a href="{{ route('analyses.index') }}" class="btn {{ (request()->routeIs('analyses.index') || request()->routeIs('analyses.show')) ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">§ 02.0 RIWAYAT ANALISIS</a>
            @can('run-analysis')
            <a href="{{ route('analyses.create') }}" class="btn {{ request()->routeIs('analyses.create') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;color:var(--color-primary);border-color:var(--color-primary);">§ 02.1 + MULAI ANALISIS BARU</a>
            @endcan
            @can('test-single-prediction')
            <a href="{{ route('predict.index') }}" class="btn {{ request()->routeIs('predict.*') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">§ 03.0 LIVE PREDICT SANDBOX</a>
            <a href="{{ route('scraper.status') }}" class="btn {{ request()->routeIs('scraper.*') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">§ 04.0 MONITOR TELEMETRI SCRAPER</a>
            @endcan
            @can('manage-users')
            <a href="{{ route('admin.users.index') }}" class="btn {{ request()->routeIs('admin.users.*') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">§ 05.0 MANAJEMEN PENGGUNA</a>
            @endcan
        </nav>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         GLOBAL COMMAND PALETTE (CTRL + K)
    ════════════════════════════════════════════════════════════ --}}
    <div x-show="cmdOpen" x-cloak class="cmd-palette-backdrop" @click.self="cmdOpen = false">
        <div class="cmd-palette-box" x-data="{
            query: '',
            commands: [
                { title: '§ 01.0 Buka Overview Dashboard', url: '{{ route('dashboard') }}', tag: 'DASHBOARD' },
                { title: '§ 02.0 Lihat Riwayat Analisis', url: '{{ route('analyses.index') }}', tag: 'ANALISIS' },
                { title: '§ 02.1 Mulai Investigasi / Analisis Baru', url: '{{ route('analyses.create') }}', tag: 'PIPELINE' },
                { title: '§ 03.0 Uji Kalimat Teks (Live Predict)', url: '{{ route('predict.index') }}', tag: 'SANDBOX' },
                { title: '§ 04.0 Cek Koneksi & Sesi Scraper Playwright', url: '{{ route('scraper.status') }}', tag: 'TELEMETRY' },
                { title: '§ 00.0 Edit Profil & Password Saya', url: '{{ route('profile.edit') }}', tag: 'AKUN' }
            ],
            filtered() {
                if (!this.query.trim()) return this.commands;
                return this.commands.filter(c => c.title.toLowerCase().includes(this.query.toLowerCase()) || c.tag.toLowerCase().includes(this.query.toLowerCase()));
            }
        }">
            {{-- Search Bar --}}
            <div style="display:flex;align-items:center;padding:0.75rem 1rem;border-bottom:2px solid #0A0A0A;gap:0.75rem;background:#FFFFFF;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0A0A0A" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" x-model="query" x-ref="cmdInput" x-init="$watch('cmdOpen', val => { if(val) setTimeout(() => $refs.cmdInput.focus(), 50) })"
                       placeholder="Ketik perintah navigasi atau fitur..."
                       style="flex:1;border:none;outline:none;font-family:var(--font-mono);font-size:0.875rem;font-weight:700;color:#0A0A0A;background:transparent;">
                <kbd style="background:var(--color-surface-2);border:1px solid #0A0A0A;padding:2px 6px;font-family:var(--font-mono);font-size:0.6875rem;">ESC</kbd>
            </div>

            {{-- Command List --}}
            <div style="max-height:280px;overflow-y:auto;padding:0.5rem;">
                <template x-for="(cmd, idx) in filtered()" :key="idx">
                    <a :href="cmd.url" style="display:flex;align-items:center;justify-content:space-between;padding:0.625rem 0.75rem;border-bottom:1px solid var(--color-border-subtle);text-decoration:none;transition:background 0.1s;"
                       onmouseover="this.style.background='var(--color-surface-2)'" onmouseout="this.style.background=''">
                        <span style="font-family:var(--font-mono);font-size:0.8125rem;font-weight:700;color:#0A0A0A;" x-text="cmd.title"></span>
                        <span class="badge badge-mono" style="font-size:0.625rem;" x-text="cmd.tag"></span>
                    </a>
                </template>
            </div>

            {{-- Footer Info --}}
            <div style="padding:0.5rem 0.75rem;background:var(--color-surface-2);border-top:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);display:flex;justify-content:space-between;">
                <span>PILIH DENGAN KLIK / ENTER</span>
                <span>HATESENSE MONOGRAPH v2.4</span>
            </div>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-cloak
         x-init="setTimeout(() => show = false, 6000)"
         style="position:fixed;top:4.5rem;right:1.5rem;z-index:200;max-width:380px;">
        <div style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;padding:0.75rem 1rem;display:flex;align-items:flex-start;gap:0.75rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0A0A0A" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;text-transform:uppercase;color:#0A0A0A;margin:0 0 2px;">PERINGATAN SISTEM</p>
                <p style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;line-height:1.4;">{{ session('error') }}</p>
            </div>
        </div>
    </div>
    @endif

    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-cloak
         x-init="setTimeout(() => show = false, 5000)"
         style="position:fixed;top:4.5rem;right:1.5rem;z-index:200;max-width:380px;">
        <div style="background:var(--color-primary-bg);border:2px solid var(--color-primary);box-shadow:4px 4px 0 #0A0A0A;padding:0.75rem 1rem;display:flex;align-items:flex-start;gap:0.75rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
            <div>
                <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;text-transform:uppercase;color:var(--color-primary);margin:0 0 2px;">BERHASIL</p>
                <p style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;line-height:1.4;">{{ session('success') }}</p>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         MAIN MONOGRAPH WORKSPACE
    ════════════════════════════════════════════════════════════ --}}
    <main class="monograph-content">
        {{-- Breadcrumb Strip --}}
        @hasSection('breadcrumb')
        <div style="padding-bottom:1rem;margin-bottom:1.5rem;border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
            <div style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:var(--color-text-muted);display:flex;align-items:center;gap:0.5rem;">
                <span style="color:#0A0A0A;">HATESENSE LAB</span>
                <span>/</span>
                @yield('breadcrumb')
            </div>
            <div style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-subtle);">
                [SESSION: ACTIVE · {{ date('Y-m-d') }}]
            </div>
        </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
