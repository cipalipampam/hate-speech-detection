{{-- ════════════════════════════════════════════════════════════════════
     STATE 1: PIPELINE PROGRESS & SUMMARY (Swiss Monograph Stepper)
════════════════════════════════════════════════════════════════════ --}}
<div x-show="!showDetail || status === 'running' || status === 'queued'" x-cloak style="margin-bottom:2rem;">
    <div class="card" style="padding:1.5rem;border-left:6px solid #0A0A0A;box-shadow:3px 3px 0 #0A0A0A;">
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #0A0A0A;padding-bottom:1rem;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:36px;height:36px;background:#0A0A0A;color:#FFFFFF;display:flex;align-items:center;justify-content:center;font-family:var(--font-mono);font-weight:900;font-size:1.125rem;">
                    <template x-if="status === 'completed'"><span>✓</span></template>
                    <template x-if="status !== 'completed'"><span>⟳</span></template>
                </div>
                <div>
                    <h2 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0;"
                        x-text="status === 'completed' ? 'AI Pipeline Completed' : (status === 'failed' ? 'AI Pipeline Failed' : 'AI Pipeline Running')">
                        {{ $analysis->status === 'failed' ? 'AI Pipeline Failed' : ($analysis->status === 'completed' ? 'AI Pipeline Completed' : 'AI Pipeline Running') }}
                    </h2>
                    <p style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);margin:2px 0 0;" x-text="pipelineMessage">
                        {{ $analysis->status === 'failed' ? ($analysis->error_message ?? 'The AI pipeline failed. Check the AI server logs.') : '' }}
                    </p>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:0.625rem;">
                <template x-if="status === 'running'">
                    <div style="display:flex;align-items:center;gap:0.5rem;background:var(--color-surface-2);border:1px solid #0A0A0A;padding:0.35rem 0.625rem;font-family:var(--font-mono);font-size:0.6875rem;">
                        <span class="telemetry-dot pulse" style="background-color:var(--color-primary);width:8px;height:8px;"></span>
                        <span style="font-weight:800;color:#0A0A0A;">POLLING ENGINE TELEMETRY...</span>
                    </div>
                </template>
                <template x-if="status === 'completed'">
                    <span class="badge badge-safe">AI PIPELINE COMPLETED</span>
                </template>
                <template x-if="status === 'failed'">
                    <span class="badge" style="background:#FEE2E2;color:#991B1B;border-color:#991B1B;">EXECUTION FAILED</span>
                </template>
                @if($analysis->status === 'failed')
                    <span class="badge" style="background:#FEE2E2;color:#991B1B;border-color:#991B1B;">EXECUTION FAILED</span>
                @endif
            </div>
        </div>

        {{-- Sharp Progress Track --}}
        <div class="progress-track-sharp" style="margin-bottom:1.5rem;background:var(--color-surface-2);border:1px solid #0A0A0A;height:8px;">
            <div class="progress-fill-sharp"
                 :style="'background:' + (status === 'completed' ? 'var(--color-primary)' : 'var(--color-danger)') + '; width:' + (status === 'completed' ? 100 : (status === 'failed' ? 100 : (pipelineStep * 25))) + '%; transition: width 0.4s ease;'"></div>
        </div>

        {{-- Control Parameters & Sequential Pipeline Steps --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(280px, 100%), 1fr));gap:1.25rem;">
            {{-- Control Parameters --}}
            <div class="card-flat" style="padding:1rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;">
                <div>
                    <span class="stat-block-label">ANALYSIS CONTROL PARAMETERS</span>
                    <p style="font-family:var(--font-mono);font-size:0.8125rem;margin:0.5rem 0 0.25rem;">
                        TARGET: <strong>{{ strtoupper($analysis->platform) }}</strong> · LIMIT/KEYWORD: <strong>{{ $analysis->max_links }} POSTS</strong>
                    </p>
                    <div style="display:flex;flex-wrap:wrap;gap:0.25rem;margin-top:0.5rem;">
                        @foreach($analysis->keywords ?? [] as $kw)
                            <span class="badge badge-mono">{{ $kw }}</span>
                        @endforeach
                    </div>
                </div>

                <div style="border-top:1px solid var(--color-border-subtle);padding-top:0.5rem;display:flex;justify-content:space-between;align-items:center;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">
                    <span>COMPUTE TIME:</span>
                    <strong style="color:#0A0A0A;" x-text="execTime ? Math.round(execTime) + ' SECONDS' : 'RUNNING...'"></strong>
                </div>
            </div>

            {{-- 4 Pipeline Steps --}}
            <div style="display:flex;flex-direction:column;gap:0.375rem;">
                <span class="stat-block-label" style="margin-bottom:0.15rem;">SEQUENTIAL PIPELINE</span>

                {{-- Step 1 --}}
                <div :style="(pipelineStep > 1 || status === 'completed') ? 'padding:0.45rem 0.65rem;background:#FFFFFF;border:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : (pipelineStep === 1 ? 'padding:0.45rem 0.65rem;background:var(--color-danger);border:2px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : 'padding:0.45rem 0.65rem;background:var(--color-surface-2);border:1px solid var(--color-border-subtle);font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;align-items:center;justify-content:space-between;')">
                    <span x-text="(pipelineStep > 1 || status === 'completed') ? '✓ 01. Verify Sessions' : (pipelineStep === 1 ? '▶ 01. Verify Sessions' : '○ 01. Verify Sessions')"></span>
                    <span class="badge badge-safe" style="font-size:0.5625rem;" x-show="pipelineStep > 1 || status === 'completed'">COMPLETED</span>
                    <span class="badge badge-black" style="font-size:0.5625rem;" x-show="pipelineStep === 1 && status !== 'completed'">RUNNING</span>
                </div>

                {{-- Step 2 --}}
                <div :style="(pipelineStep > 2 || status === 'completed') ? 'padding:0.45rem 0.65rem;background:#FFFFFF;border:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : (pipelineStep === 2 ? 'padding:0.45rem 0.65rem;background:var(--color-danger);border:2px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : 'padding:0.45rem 0.65rem;background:var(--color-surface-2);border:1px solid var(--color-border-subtle);font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;align-items:center;justify-content:space-between;')">
                    <span x-text="(pipelineStep > 2 || status === 'completed') ? '✓ 02. Scrape Social Media' : (pipelineStep === 2 ? '▶ 02. Scrape Social Media' : '○ 02. Scrape Social Media')"></span>
                    <span class="badge badge-safe" style="font-size:0.5625rem;" x-show="pipelineStep > 2 || status === 'completed'">COMPLETED</span>
                    <span class="badge badge-black" style="font-size:0.5625rem;" x-show="pipelineStep === 2 && status !== 'completed'">RUNNING</span>
                </div>

                {{-- Step 3 --}}
                <div :style="(pipelineStep > 3 || status === 'completed') ? 'padding:0.45rem 0.65rem;background:#FFFFFF;border:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : (pipelineStep === 3 ? 'padding:0.45rem 0.65rem;background:var(--color-danger);border:2px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : 'padding:0.45rem 0.65rem;background:var(--color-surface-2);border:1px solid var(--color-border-subtle);font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;align-items:center;justify-content:space-between;')">
                    <span x-text="(pipelineStep > 3 || status === 'completed') ? '✓ 03. Preprocessing' : (pipelineStep === 3 ? '▶ 03. Preprocessing' : '○ 03. Preprocessing')"></span>
                    <span class="badge badge-safe" style="font-size:0.5625rem;" x-show="pipelineStep > 3 || status === 'completed'">COMPLETED</span>
                    <span class="badge badge-black" style="font-size:0.5625rem;" x-show="pipelineStep === 3 && status !== 'completed'">RUNNING</span>
                </div>

                {{-- Step 4 --}}
                <div :style="status === 'completed' ? 'padding:0.45rem 0.65rem;background:#FFFFFF;border:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : (pipelineStep === 4 ? 'padding:0.45rem 0.65rem;background:var(--color-danger);border:2px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;display:flex;align-items:center;justify-content:space-between;' : 'padding:0.45rem 0.65rem;background:var(--color-surface-2);border:1px solid var(--color-border-subtle);font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);display:flex;align-items:center;justify-content:space-between;')">
                    <span x-text="status === 'completed' ? '✓ 04. IndoBERT Inference (L1 & L2)' : (pipelineStep === 4 ? '▶ 04. IndoBERT Inference (L1 & L2)' : '○ 04. IndoBERT Inference (L1 & L2)')"></span>
                    <span class="badge badge-safe" style="font-size:0.5625rem;" x-show="status === 'completed'">COMPLETED</span>
                    <span class="badge badge-black" style="font-size:0.5625rem;" x-show="pipelineStep === 4 && status !== 'completed'">RUNNING</span>
                </div>
            </div>
        </div>

        {{-- Telemetry Terminal Log Banner --}}
        <div style="background:#0A0A0A;color:#FFFFFF;padding:0.75rem 1rem;border:1px solid #0A0A0A;font-family:var(--font-mono);font-size:0.75rem;display:flex;align-items:center;justify-content:space-between;margin-top:1.25rem;margin-bottom:1.5rem;box-shadow:inset 0 1px 3px rgba(0,0,0,0.5);">
            <div style="display:flex;align-items:center;gap:0.625rem;">
                <span style="color:var(--color-primary);font-weight:800;">[SYSTEM_LOG]</span>
                <span x-text="pipelineMessage">
                    {{ $analysis->status === 'failed' ? ($analysis->error_message ?? 'The AI pipeline failed. Check the AI server logs.') : '' }}
                </span>
            </div>
            <div style="color:var(--color-text-subtle);font-size:0.6875rem;" x-show="status === 'running'">
                [SYNC: 3s]
            </div>
        </div>

        {{-- Completion Summary & Explicit Action Button --}}
        <div x-show="status === 'completed'" style="background:var(--color-surface-2);border:1px solid #0A0A0A;border-top:2px solid #0A0A0A;padding:1.25rem;margin-top:1.5rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
                <div>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                        <span class="badge badge-safe">PROCESS COMPLETE 100%</span>
                        <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:#0A0A0A;">STATUS: DATA READY FOR REVIEW</span>
                    </div>
                    <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0;">
                        Scraper extraction and IndoBERT classification are complete. Open the interactive research desk below to review the data dossier.
                    </p>
                </div>
                <div style="font-family:var(--font-mono);font-size:0.75rem;text-align:right;">
                    <span style="display:block;color:var(--color-text-muted);">TOTAL RECORDS ANALYZED:</span>
                    <strong style="font-size:1.125rem;color:#0A0A0A;" x-text="numberFormat(stats?.total_data || 0) + ' POSTS'"></strong>
                </div>
            </div>

            <button type="button"
                    @click="showDetail = true; $nextTick(() => renderCharts())"
                    class="btn btn-primary btn-lg"
                    style="width:100%;font-weight:900;letter-spacing:0.04em;height:46px;box-shadow:3px 3px 0 #0A0A0A;display:flex;justify-content:center;">
                <span>VIEW ANALYSIS RESULTS & RESEARCH DESK [→]</span>
            </button>
        </div>

    </div>
</div>

