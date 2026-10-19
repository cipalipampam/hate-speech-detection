{{-- ═══════════════════════════════════════════════════════════
     MOBILE / COLLAPSED MENU DRAWER
════════════════════════════════════════════════════════════ --}}
<div x-show="mobileMenu" x-cloak @click.outside="mobileMenu = false"
     style="position:fixed;top:56px;left:0;right:0;background:#FFFFFF;border-bottom:2px solid #0A0A0A;box-shadow:0 8px 0 rgba(0,0,0,0.15);z-index:49;padding:1rem 1.25rem;">
    <div style="border-bottom:1px solid var(--color-border-subtle);padding-bottom:0.5rem;margin-bottom:0.75rem;display:flex;justify-content:space-between;align-items:center;">
        <span class="stat-block-label">§ SYSTEM NAVIGATION INDEX</span>
        <button @click="mobileMenu = false" style="background:none;border:none;font-family:var(--font-mono);font-size:1rem;font-weight:900;cursor:pointer;">✕</button>
    </div>
    <nav style="display:flex;flex-direction:column;gap:0.375rem;">
        <a href="{{ route('dashboard') }}" class="btn {{ request()->routeIs('dashboard') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">
            § 01.0 OVERVIEW
        </a>
        <a href="{{ route('analyses.index') }}" class="btn {{ request()->routeIs('analyses.*') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">
            § 02.0 BATCH ANALYSES
        </a>
        @can('test-single-prediction')
        <a href="{{ route('predict.index') }}" class="btn {{ request()->routeIs('predict.*') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">
            § 03.0 LIVE CLASSIFIER
        </a>
        <a href="{{ route('scraper.status') }}" class="btn {{ request()->routeIs('scraper.*') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">
            § 04.0 SYSTEM TELEMETRY
        </a>
        @endcan
        @can('manage-users')
        <a href="{{ route('admin.users.index') }}" class="btn {{ request()->routeIs('admin.users.*') ? 'btn-primary' : 'btn-outline' }}" style="justify-content:flex-start;">
            § 05.0 USER DIRECTORY
        </a>
        @endcan
    </nav>
</div>

