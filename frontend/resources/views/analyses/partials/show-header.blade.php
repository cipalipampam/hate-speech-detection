{{-- ── Monograph Dossier Header ── --}}
<div style="border-bottom:2px solid #0A0A0A;padding-bottom:1.25rem;margin-bottom:1.75rem;display:flex;align-items:flex-start;justify-content:space-between;gap:1.25rem;flex-wrap:wrap;">
    <div>
        <div style="display:flex;align-items:center;gap:0.625rem;margin-bottom:0.375rem;flex-wrap:wrap;">
            <span class="badge badge-black">DOSSIER #{{ str_pad($analysis->id, 4, '0', STR_PAD_LEFT) }}</span>
            <template x-if="status === 'completed'">
                <span class="badge badge-safe">COMPLETED (VERIFIED)</span>
            </template>
            <template x-if="status === 'running'">
                <span class="badge badge-hate" style="animation:telemetry-pulse 1.5s infinite;">PIPELINE RUNNING</span>
            </template>
            <template x-if="status === 'queued'">
                <span class="badge badge-mono">QUEUED</span>
            </template>
            <template x-if="status === 'failed'">
                <span class="badge" style="background:#FEE2E2;color:#991B1B;border-color:#991B1B;">EXECUTION FAILED</span>
            </template>
        </div>

        <h1 style="font-size:1.875rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0 0 0.375rem;line-height:1.15;">
            {{ $analysis->title }}
        </h1>

        <div style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;gap:1rem;flex-wrap:wrap;">
            <span>PLATFORM: <strong style="color:#0A0A0A;">{{ strtoupper($analysis->platform) }}</strong></span>
            <span>·</span>
            <span>MODE: <strong style="color:#0A0A0A;">{{ strtoupper($analysis->search_mode) }}</strong></span>
            <span>·</span>
            <span>LIMIT/KEYWORD: <strong style="color:#0A0A0A;">{{ $analysis->max_links }} URLS</strong></span>
            <span>·</span>
            <span>RESEARCHER: <strong style="color:#0A0A0A;">{{ $analysis->user->name ?? 'SYSTEM' }}</strong></span>
            <span>·</span>
            <span>CREATED: <strong style="color:#0A0A0A;">{{ $analysis->created_at->format('Y-m-d H:i') }}</strong></span>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:0.625rem;flex-wrap:wrap;">
        <template x-if="status === 'completed'">
            <button type="button" @click="showDetail = !showDetail; if(showDetail) $nextTick(() => renderCharts())" class="btn btn-outline btn-sm" style="font-family:var(--font-mono);font-size:0.6875rem;">
                <span x-text="showDetail ? '⟳ PIPELINE VIEW' : '→ OPEN RESEARCH DESK'"></span>
            </button>
        </template>
        @can('export-reports')
        <template x-if="status === 'completed'">
            <a :href="exportUrl" class="btn btn-outline btn-sm" style="gap:0.375rem;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>DOWNLOAD DATASET (CSV)</span>
            </a>
        </template>
        @endcan
        @can('run-analysis')
        <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-sm">
            <span>+ NEW ANALYSIS</span>
        </a>
        @endcan
    </div>
</div>

