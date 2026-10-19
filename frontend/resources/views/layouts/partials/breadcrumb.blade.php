{{-- ═══════════════════════════════════════════════════════════
     BREADCRUMB REVIER STRIP
════════════════════════════════════════════════════════════ --}}
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

