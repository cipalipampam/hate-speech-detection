{{-- ── 5 Stat Blocks Summary ── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));gap:0.875rem;margin-bottom:2rem;">
    <div class="stat-block">
        <span class="stat-block-label">TOTAL DATA (N)</span>
        <span class="stat-block-val" x-text="numberFormat(stats?.total_data || 0)"></span>
        <div class="stat-block-meta">[RECORDS DOWNLOADED]</div>
    </div>

    <div class="stat-block" style="background:var(--color-danger-bg);border-top:3px solid var(--color-danger);">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="stat-block-label" style="color:#0A0A0A;">HATE SPEECH</span>
            <span class="badge badge-hate" style="font-size:0.6rem;">FLAG</span>
        </div>
        <span class="stat-block-val" style="color:#0A0A0A;" x-text="numberFormat(stats?.hate_speech_count || 0)"></span>
        <div class="stat-block-meta" style="border-color:#0A0A0A;color:#0A0A0A;">[LEVEL 1: HATE]</div>
    </div>

    <div class="stat-block" style="background:var(--color-primary-bg);border-top:3px solid var(--color-primary);">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="stat-block-label" style="color:var(--color-primary);">NON-HATE CONTENT</span>
            <span class="badge badge-safe" style="font-size:0.6rem;">SAFE</span>
        </div>
        <span class="stat-block-val" style="color:var(--color-primary);" x-text="numberFormat(stats?.non_hate_speech_count || 0)"></span>
        <div class="stat-block-meta" style="border-color:var(--color-primary);color:var(--color-primary);">[LEVEL 1: NON-HATE]</div>
    </div>

    <div class="stat-block">
        <span class="stat-block-label">HATE SPEECH RATE</span>
        <span class="stat-block-val" x-text="(stats?.hate_speech_pct || 0) + '%'"></span>
        <div class="stat-block-meta">[HATE PERCENTAGE]</div>
    </div>

    <div class="stat-block">
        <span class="stat-block-label">EXECUTION TIME</span>
        <span class="stat-block-val" x-text="execTime ? Math.round(execTime) + 's' : '—'"></span>
        <div class="stat-block-meta">[COMPUTE DURATION]</div>
    </div>
</div>

