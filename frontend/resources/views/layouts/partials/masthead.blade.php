{{-- ═══════════════════════════════════════════════════════════
     SWISS MONOGRAPH TOP MASTHEAD
════════════════════════════════════════════════════════════ --}}
<header class="masthead">
    {{-- Brand / Masthead Title --}}
    <div style="display:flex;align-items:center;height:100%;flex-shrink:0;">
        <a href="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:0.625rem;text-decoration:none;padding-right:0.875rem;border-right:1px solid var(--color-border);height:100%;flex-shrink:0;">
            <div style="background:#0A0A0A;color:#FFFFFF;font-family:var(--font-mono);font-weight:900;font-size:0.875rem;padding:0.25rem 0.45rem;line-height:1;border:1px solid #0A0A0A;flex-shrink:0;">
                HS
            </div>
            <div style="white-space:nowrap;">
                <span style="font-weight:900;font-size:0.875rem;color:#0A0A0A;letter-spacing:-0.03em;display:block;line-height:1.1;">
                    HATESENSE <span style="color:var(--color-primary);">ID</span>
                </span>
                <span class="masthead-brand-sub" style="font-family:var(--font-mono);font-size:0.5625rem;color:var(--color-text-muted);letter-spacing:0.04em;text-transform:uppercase;">
                    RESEARCH LAB · V2.4
                </span>
            </div>
        </a>

        {{-- Monograph Navigation --}}
        <nav class="masthead-nav">
            <a href="{{ route('dashboard') }}" class="masthead-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                § 01.0 OVERVIEW
            </a>

            @can('view-dashboard')
            <a href="{{ route('analyses.index') }}" class="masthead-link {{ request()->routeIs('analyses.*') ? 'active' : '' }}">
                § 02.0 ANALYSES
            </a>
            @endcan

            @can('test-single-prediction')
            <a href="{{ route('predict.index') }}" class="masthead-link {{ request()->routeIs('predict.*') ? 'active' : '' }}">
                § 03.0 CLASSIFIER
            </a>

            <a href="{{ route('scraper.status') }}" class="masthead-link {{ request()->routeIs('scraper.*') ? 'active' : '' }}">
                § 04.0 TELEMETRY
            </a>
            @endcan

            @can('manage-users')
            <a href="{{ route('admin.users.index') }}" class="masthead-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                § 05.0 USER DIRECTORY
            </a>
            @endcan
        </nav>
    </div>

    {{-- Right Side Telemetry & Controls --}}
    <div style="display:flex;align-items:center;gap:0.5rem;flex-shrink:0;">
        {{-- Telemetry Server Box (Minimalist Status Indicator) --}}
        <div x-data="fastApiHeaderTelemetry('{{ route('fastapi.health') }}')"
             class="badge badge-mono"
             style="height:32px;width:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;cursor:help;"
             :title="isOnline ? 'FastAPI Engine: ONLINE (200 OK)' : 'FastAPI Engine: OFFLINE / INACTIVE'">
            <span class="telemetry-dot pulse"
                  :style="isOnline ? 'background-color:var(--color-teal);' : 'background-color:var(--color-danger);'"
                  style="background-color:var(--color-teal);width:10px;height:10px;"></span>
        </div>

        {{-- User Account Dropdown --}}
        <div x-data="{ userMenu: false }" style="position:relative;">
            <button @click="userMenu = !userMenu" class="btn btn-outline btn-sm" style="height:32px;padding:0 0.5rem;gap:0.35rem;">
                <span style="font-weight:800;color:var(--color-primary);">[</span>
                <span style="max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.75rem;">{{ auth()->user()->name ?? 'USER' }}</span>
                <span style="font-weight:800;color:var(--color-primary);">]</span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </button>

            <div x-show="userMenu" x-cloak @click.outside="userMenu = false"
                 style="position:absolute;top:calc(100% + 4px);right:0;width:220px;background:#FFFFFF;border:1px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;z-index:100;">
                <div style="padding:0.625rem 0.75rem;background:var(--color-surface-2);border-bottom:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.6875rem;">
                    <span style="color:var(--color-text-muted);display:block;">RESEARCHER ACCOUNT:</span>
                    <span style="font-weight:700;color:#0A0A0A;">{{ auth()->user()->email ?? '' }}</span>
                    <span class="badge badge-mono" style="margin-top:0.25rem;font-size:0.625rem;">
                        ROLE: {{ strtoupper(auth()->user()?->getRoleNames()?->first() ?? 'NO ROLE') }}
                    </span>
                </div>

                <a href="{{ route('profile.edit') }}"
                   style="display:flex;align-items:center;gap:0.5rem;padding:0.625rem 0.75rem;font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:#0A0A0A;text-decoration:none;border-bottom:1px solid var(--color-border-subtle);"
                   onmouseover="this.style.background='var(--color-surface-2)'" onmouseout="this.style.background=''">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    § 00.1 PROFILE SETTINGS
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            style="width:100%;text-align:left;display:flex;align-items:center;gap:0.5rem;padding:0.625rem 0.75rem;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:var(--color-crimson);background:none;border:none;cursor:pointer;"
                            onmouseover="this.style.background='var(--color-crimson-bg)'" onmouseout="this.style.background=''">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        SIGN OUT
                    </button>
                </form>
            </div>
        </div>

        {{-- Mobile Menu Hamburger --}}
        <button @click="mobileMenu = !mobileMenu" class="btn btn-outline btn-sm masthead-hamburger" style="height:32px;padding:0 0.5rem;" title="Open navigation menu">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
    </div>
</header>

