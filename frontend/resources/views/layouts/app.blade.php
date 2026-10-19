<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Multi-platform hate speech detection using Hierarchical IndoBERT')">
    <title>@yield('title', 'Analysis Portal') — HateSense ID Lab</title>

    {{-- Google Fonts: Plus Jakarta Sans & JetBrains Mono --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/overrides.css') }}">
    @stack('head')
</head>
<body class="antialiased" style="min-height:100vh;display:flex;flex-direction:column;" x-data="{ cmdOpen: false, mobileMenu: false }"
      @keydown.window.ctrl.k.prevent="cmdOpen = !cmdOpen"
      @keydown.window.meta.k.prevent="cmdOpen = !cmdOpen"
      @keydown.window.escape="cmdOpen = false">

    {{-- Masthead Top Navigation --}}
    @include('layouts.partials.masthead')

    {{-- Mobile Drawer Navigation --}}
    @include('layouts.partials.mobile-drawer')

    {{-- Global Command Palette (Ctrl+K) --}}
    @include('layouts.partials.command-palette')

    {{-- Flash Notifications --}}
    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-cloak
         x-init="setTimeout(() => show = false, 6000)"
         style="position:fixed;top:4.5rem;right:1.5rem;z-index:200;max-width:380px;">
        <div style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;padding:0.75rem 1rem;display:flex;align-items:flex-start;gap:0.75rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0A0A0A" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;text-transform:uppercase;color:#0A0A0A;margin:0 0 2px;">SYSTEM WARNING</p>
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
                <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;text-transform:uppercase;color:var(--color-primary);margin:0 0 2px;">SUCCESS</p>
                <p style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;line-height:1.4;">{{ session('success') }}</p>
            </div>
        </div>
    </div>
    @endif

    {{-- Main Monograph Workspace --}}
    <main class="monograph-content">
        @include('layouts.partials.breadcrumb')
        @yield('content')
    </main>

    {{-- Global Monograph Footer --}}
    @include('layouts.partials.footer')

    @stack('scripts')
</body>
</html>
