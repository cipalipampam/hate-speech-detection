{{-- ── Dual Charts (Sentimen & Kategori Level 2) ── --}}
<div class="charts-row" style="display:grid;grid-template-columns:minmax(0, 1fr) minmax(0, 1.6fr);gap:1.25rem;margin-bottom:2.5rem;">
    <div class="card" style="padding:1.25rem;min-width:0;">
        <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.5rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;">
            <span class="stat-block-label">LEVEL 1 SENTIMENT DISTRIBUTION</span>
            <span class="badge badge-mono">ACTIVE SESSION</span>
        </div>
        <div id="session-sentiment-chart"></div>
    </div>

    <div class="card" style="padding:1.25rem;min-width:0;">
        <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.5rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;">
            <span class="stat-block-label">SIX-CLASS LEVEL 2 TAXONOMY</span>
            <span class="badge badge-mono">INDOBERT-V2</span>
        </div>
        <div id="session-level2-chart"></div>
    </div>
</div>

