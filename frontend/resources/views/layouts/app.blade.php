<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Sistem Deteksi Ujaran Kebencian Multi-Platform menggunakan Hierarchical IndoBERT')">
    <title>@yield('title', 'Portal Analisis') — HateSense ID</title>

    {{-- Google Fonts: Plus Jakarta Sans --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <style>
        [x-cloak] { display: none !important; }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="antialiased">

    {{-- Sidebar Overlay (Mobile) --}}
    <div id="sidebar-overlay"
         class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden"
         onclick="closeSidebar()"></div>

    {{-- Sidebar --}}
    <aside id="sidebar" class="sidebar">
        {{-- Logo --}}
        <div class="sidebar-logo">
            <div style="width:36px;height:36px;border-radius:0.625rem;background:linear-gradient(135deg, #E76F51, #F4A261);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </div>
            <div>
                <p style="font-weight:800;font-size:0.9375rem;color:#FFFFFF;line-height:1.1;">HateSense <span style="color:#E76F51;">ID</span></p>
                <p style="font-size:0.6875rem;color:rgba(255,255,255,0.45);font-weight:400;">Hate Speech Detection</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 py-2 px-0.5">

            {{-- Dashboard --}}
            <div class="sidebar-nav-group">
                <p class="sidebar-nav-label">Utama</p>
                <a href="{{ route('dashboard') }}" class="sidebar-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
                    </svg>
                    Dashboard
                </a>
            </div>

            {{-- Analisis --}}
            @can('view-dashboard')
            <div class="sidebar-nav-group">
                <p class="sidebar-nav-label">Analisis</p>
                <a href="{{ route('analyses.index') }}" class="sidebar-nav-link {{ (request()->routeIs('analyses.index') || request()->routeIs('analyses.show')) ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    Riwayat Analisis
                </a>

                @can('run-analysis')
                <a href="{{ route('analyses.create') }}" class="sidebar-nav-link {{ request()->routeIs('analyses.create') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    Analisis Baru
                </a>
                @endcan
            </div>
            @endcan

            {{-- Tools --}}
            @can('test-single-prediction')
            <div class="sidebar-nav-group">
                <p class="sidebar-nav-label">Tools</p>
                <a href="{{ route('predict.index') }}" class="sidebar-nav-link {{ request()->routeIs('predict.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Live Text Classifier
                </a>
                <a href="{{ route('scraper.status') }}" class="sidebar-nav-link {{ request()->routeIs('scraper.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Monitor Scraper
                </a>
            </div>
            @endcan

            {{-- Admin --}}
            @can('manage-users')
            <div class="sidebar-nav-group">
                <p class="sidebar-nav-label">Administrasi</p>
                <a href="{{ route('admin.users.index') }}" class="sidebar-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Kelola Pengguna
                </a>
            </div>
            @endcan

        </nav>

        {{-- User Profile Footer --}}
        <div style="border-top:1px solid rgba(255,255,255,0.08);padding:1rem 1.25rem;">
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open"
                        class="w-full flex items-center gap-2.5 rounded-xl p-2 transition-colors"
                        style="background:rgba(255,255,255,0.05);"
                        onmouseover="this.style.background='rgba(255,255,255,0.10)'"
                        onmouseout="this.style.background='rgba(255,255,255,0.05)'">
                    <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#E76F51,#F4A261);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span style="font-size:0.8125rem;font-weight:700;color:#FFF;">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </span>
                    </div>
                    <div class="text-left flex-1 min-w-0">
                        <p style="font-size:0.8125rem;font-weight:600;color:#FFFFFF;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ auth()->user()->name ?? 'Pengguna' }}
                        </p>
                        <p style="font-size:0.6875rem;color:rgba(255,255,255,0.45);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ auth()->user()->getRoleNames()->first() ?? 'viewer' }}
                        </p>
                    </div>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.45)" stroke-width="2" :style="open ? 'transform:rotate(180deg)' : ''" style="transition:transform 0.2s;flex-shrink:0;">
                        <polyline points="18 15 12 9 6 15"/>
                    </svg>
                </button>

                {{-- Dropdown --}}
                <div x-show="open" x-cloak @click.outside="open = false"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     style="position:absolute;bottom:calc(100% + 8px);left:0;right:0;background:#fff;border:1px solid var(--color-border);border-radius:0.875rem;overflow:hidden;box-shadow:0 8px 24px rgba(30,58,76,0.15);">
                    <a href="{{ route('profile.edit') }}"
                       style="display:flex;align-items:center;gap:0.5rem;padding:0.625rem 1rem;font-size:0.875rem;color:var(--color-text-body);text-decoration:none;transition:background 0.15s;"
                       onmouseover="this.style.background='var(--color-surface-2)'"
                       onmouseout="this.style.background=''">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Profil Saya
                    </a>
                    <hr style="border:none;border-top:1px solid var(--color-border);margin:0.25rem 0;">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                style="display:flex;align-items:center;gap:0.5rem;padding:0.625rem 1rem;font-size:0.875rem;color:#C52B2B;background:none;border:none;width:100%;cursor:pointer;transition:background 0.15s;font-family:var(--font-sans);"
                                onmouseover="this.style.background='var(--color-danger-bg)'"
                                onmouseout="this.style.background=''">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    {{-- Topbar --}}
    <header class="topbar">
        {{-- Hamburger (Mobile) --}}
        <button class="lg:hidden btn btn-ghost btn-sm px-2" onclick="toggleSidebar()" id="hamburger-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        {{-- Page Breadcrumb --}}
        <div class="flex-1">
            @yield('breadcrumb')
        </div>

        {{-- Right Side --}}
        <div class="flex items-center gap-3">
            {{-- Notif (placeholder) --}}
            <button class="relative btn btn-ghost btn-sm px-2.5"
                    style="border-color:var(--color-border);"
                    data-tooltip="Notifikasi">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </button>

            {{-- Role Badge --}}
            @auth
            <span class="badge badge-navy capitalize" style="font-size:0.75rem;">
                {{ auth()->user()->getRoleNames()->first() ?? 'viewer' }}
            </span>
            @endauth
        </div>
    </header>

    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-cloak
         x-init="setTimeout(() => show = false, 5000)"
         style="position:fixed;top:1rem;right:1rem;z-index:200;max-width:360px;">
        <div class="alert alert-danger" style="box-shadow:0 4px 16px rgba(239,68,68,0.2);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <p style="font-size:0.875rem;font-weight:500;">{{ session('error') }}</p>
        </div>
    </div>
    @endif

    {{-- Main Content --}}
    <main class="main-content">
        <div class="page-body">
            @yield('content')
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const s = document.getElementById('sidebar');
            const o = document.getElementById('sidebar-overlay');
            s.classList.toggle('open');
            o.classList.toggle('hidden');
        }
        function closeSidebar() {
            const s = document.getElementById('sidebar');
            const o = document.getElementById('sidebar-overlay');
            s.classList.remove('open');
            o.classList.add('hidden');
        }
    </script>

    @stack('scripts')
</body>
</html>
