@extends('layouts.app')

@section('title', '§ 03.0 Live Classifier')

@section('breadcrumb')
<span style="color:#0A0A0A;">§ 03.0 LIVE CLASSIFIER</span>
@endsection

@section('content')

{{-- JSON Island: server data for live classifier page --}}
<script id="predict-server-data" type="application/json">
    {!! json_encode([
        'endpoint' => route('predict.classify'),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
</script>

<div x-data="liveClassifier()">

    {{-- ── Page Header ── --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:1.5rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                <span class="badge badge-black">LAB § 03.0</span>
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">AI LANGUAGE LAB</span>
            </div>
            <h1 style="font-size:2rem;font-weight:900;letter-spacing:-0.035em;color:#0A0A0A;margin:0;line-height:1.1;">
                LIVE TEXT CLASSIFIER
            </h1>
            <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
                Run instant single-text classification with hierarchical IndoBERT and Kamusalay normalization.
            </p>
        </div>
        <div class="badge badge-mono">MODEL: INDOBERT-BASE-UNCASED</div>
    </div>


    {{-- ══════════════════════════════════════════════════════════════
         BENTO TOP ROW — Input Bench + Batch Hero Card (separated with gap)
    ══════════════════════════════════════════════════════════════ --}}
    <div style="display:grid;grid-template-columns:minmax(0,1.8fr) minmax(0,1fr);gap:0.75rem;margin-bottom:1rem;">

        {{-- ── Input Bench (left) ── --}}
        <div class="card" style="padding:1.25rem;display:flex;flex-direction:column;gap:0.875rem;">

            {{-- Bench header --}}
            <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #0A0A0A;padding-bottom:0.625rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span style="width:10px;height:10px;background:#0A0A0A;display:inline-block;flex-shrink:0;"></span>
                    <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;letter-spacing:0.05em;">SINGLE TEXT INPUT BENCH</span>
                </div>
                <span class="badge badge-mono" style="font-size:0.5625rem;">KAMUSALAY + INDOBERT</span>
            </div>

            {{-- Quick Preset Chips --}}
            <div>
                <div style="font-family:var(--font-mono);font-size:0.625rem;font-weight:700;color:var(--color-text-muted);margin-bottom:0.5rem;letter-spacing:0.06em;">
                    QUICK PRESETS — CLICK TO LOAD SAMPLE TEXT:
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:0.375rem;">
                    <button type="button"
                            @click="inputText = 'wajarlah dia awal nya kan juga anggota freemason (yahudi).'"
                            class="btn btn-outline btn-sm"
                            style="font-size:0.6875rem;padding:0.25rem 0.625rem;text-transform:none;">
                        <span style="color:var(--color-crimson);font-weight:900;">▪</span> Hate-Baiting Hoax
                    </button>
                    <button type="button"
                            @click="inputText = 'kerjaan dewan pengkhianat rakyat: ruu yg menguntungkan penguasa dikebut, ruu yg pro rakyat gak pernah beres.'"
                            class="btn btn-outline btn-sm"
                            style="font-size:0.6875rem;padding:0.25rem 0.625rem;text-transform:none;">
                        <span style="color:var(--color-crimson);font-weight:900;">▪</span> Delegitimization
                    </button>
                    <button type="button"
                            @click="inputText = 'kapan sih anjing2 kena azab'"
                            class="btn btn-outline btn-sm"
                            style="font-size:0.6875rem;padding:0.25rem 0.625rem;text-transform:none;">
                        <span style="color:var(--color-crimson);font-weight:900;">▪</span> Dehumanization
                    </button>
                    <button type="button"
                            @click="inputText = 'tembak mati dnk biar seru'"
                            class="btn btn-outline btn-sm"
                            style="font-size:0.6875rem;padding:0.25rem 0.625rem;text-transform:none;">
                        <span style="color:var(--color-crimson);font-weight:900;">▪</span> Incitement to Violence
                    </button>
                    <button type="button"
                            @click="inputText = 'semoga semua pejabat yang dzalim dapet azab gara-gara menyengsarakan rakyat'"
                            class="btn btn-outline btn-sm"
                            style="font-size:0.6875rem;padding:0.25rem 0.625rem;text-transform:none;">
                        <span style="color:#f97316;font-weight:900;">▪</span> Personal Abuse
                    </button>
                    <button type="button"
                            @click="inputText = 'langkah transparansi ini penting banget, dpr lanjutkan biar publik ikut terlibat'"
                            class="btn btn-outline btn-sm"
                            style="font-size:0.6875rem;padding:0.25rem 0.625rem;text-transform:none;">
                        <span style="color:var(--color-primary);font-weight:900;">▪</span> Non-Hate / Neutral
                    </button>
                </div>
            </div>

            {{-- Textarea --}}
            <div>
                <label class="label" for="predict-text">RAW TEXT INPUT (VERBATIM):</label>
                <textarea id="predict-text"
                          x-model="inputText"
                          placeholder="Type or paste Indonesian text here, including slang, abbreviations, or social media dialect..."
                          class="input"
                          rows="4"
                          style="resize:vertical;font-size:0.9375rem;line-height:1.6;font-family:var(--font-sans);"></textarea>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.375rem;font-family:var(--font-mono);font-size:0.625rem;color:var(--color-text-muted);">
                    <span>PIPELINE: KAMUSALAY (15,167 TERMS) + REGEX FILTER</span>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span x-show="inputText.trim().length > 0 && inputText.trim().length < 4"
                              style="color:var(--color-crimson);font-weight:700;" x-cloak>
                            MIN. 4 CHARACTERS
                        </span>
                        <span>LENGTH: <strong style="color:#0A0A0A;" x-text="inputText.length"></strong></span>
                    </div>
                </div>
            </div>

            {{-- Run Button --}}
            <button type="button"
                    @click="classify()"
                    :disabled="loading || inputText.trim().length < 4"
                    :style="(loading || inputText.trim().length < 4) ? 'opacity:0.4;cursor:not-allowed;' : ''"
                    class="btn btn-primary btn-lg"
                    style="width:100%;height:46px;">
                <span x-show="!loading">↵ RUN INDOBERT CLASSIFICATION</span>
                <span x-show="loading" x-cloak>PROCESSING INDOBERT TENSOR...</span>
            </button>

            {{-- Error Banner --}}
            <div x-show="errorMsg" x-cloak
                 style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:2px 2px 0 #0A0A0A;padding:0.75rem 1rem;">
                <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin:0 0 2px;">INFERENCE WARNING:</p>
                <p style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;" x-text="errorMsg"></p>
            </div>

        </div>

        {{-- ── Batch Hero Card (Option 2: Minimalist Terminal) ── --}}
        @can('run-analysis')
        <div style="background:#0A0A0A;color:#FFFFFF;padding:1.375rem;display:flex;flex-direction:column;justify-content:space-between;border:1px solid #0A0A0A;box-shadow:2px 2px 0 #0A0A0A;">

            <div>
                {{-- Header Badge --}}
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                    <span class="badge" style="background:#002FA7;color:#FFFFFF;border-color:#002FA7;">CORPUS INGESTION</span>
                    <span class="badge" style="background:#1a1a1a;color:#4ade80;border-color:#166534;font-size:0.5625rem;">● PIPELINE READY</span>
                </div>

                {{-- Title --}}
                <h3 style="font-size:1.125rem;font-weight:900;color:#FFFFFF;margin:0 0 0.5rem;line-height:1.25;letter-spacing:-0.025em;">
                    Large-Scale Research Pipeline
                </h3>
                <p style="font-family:var(--font-sans);font-size:0.8125rem;color:#a3a3a3;margin:0 0 1.25rem;line-height:1.55;">
                    Need comprehensive sentiment telemetry? Run parallel crawlers across social platforms to capture discourse waves over time.
                </p>

                {{-- Stark Numbered Points (Clean & Editorial) --}}
                <div style="display:flex;flex-direction:column;gap:0.75rem;font-family:var(--font-mono);font-size:0.6875rem;line-height:1.45;">
                    <div style="display:flex;align-items:flex-start;gap:0.625rem;color:#d4d4d4;">
                        <span style="color:#FACC15;font-weight:900;font-size:0.75rem;">01/</span>
                        <span><strong style="color:#FFFFFF;">Targeted Crawling:</strong> Automated Playwright crawler for 𝕏 and Threads.</span>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:0.625rem;color:#d4d4d4;">
                        <span style="color:#FACC15;font-weight:900;font-size:0.75rem;">02/</span>
                        <span><strong style="color:#FFFFFF;">CSV Ingestion:</strong> Direct bulk upload for offline researcher datasets.</span>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:0.625rem;color:#d4d4d4;">
                        <span style="color:#FACC15;font-weight:900;font-size:0.75rem;">03/</span>
                        <span><strong style="color:#FFFFFF;">Full Telemetry:</strong> Macro trends, toxicity ratios & 6-class subcategories.</span>
                    </div>
                </div>
            </div>

            {{-- CTA --}}
            <a href="{{ route('analyses.create') }}"
               class="btn btn-hazard"
               style="width:100%;justify-content:center;margin-top:1.5rem;">
                + START BATCH ANALYSIS [→]
            </a>

        </div>
        @endcan

        @cannot('run-analysis')
        <div style="background:#1c1c1c;padding:1.25rem;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;gap:0.5rem;min-height:220px;border:1px solid #333;">
            <span class="badge badge-mono" style="opacity:0.5;">BATCH MODE</span>
            <p style="font-family:var(--font-mono);font-size:0.75rem;color:#4b5563;margin:0;">Batch analysis is not available for your account.</p>
        </div>
        @endcannot

    </div>{{-- /bento-top --}}


    {{-- ══════════════════════════════════════════════════════════════
         BENTO BOTTOM ROW — 3 Result Cards (shown after classify)
    ══════════════════════════════════════════════════════════════ --}}
    <div x-show="result" x-cloak>

        {{-- Result header --}}
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.625rem;flex-wrap:wrap;">
            <span class="badge badge-black">TENSOR INFERENCE RESULT</span>
            <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:var(--color-text-muted);">TWO-STAGE INDOBERT</span>
            <span class="badge badge-safe" style="font-size:0.5625rem;">STATUS: SUCCESS</span>
            <span class="badge badge-mono" style="font-size:0.5625rem;" x-text="'LATENCY: ' + latency + 'ms'"></span>
        </div>

        {{-- 3 cards grid --}}
        <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1fr);gap:0.75rem;">

            {{-- Card 01: Level 1 Sentiment --}}
            <div :style="result && result.label_lvl1 === 'hate_speech'
                         ? 'background:var(--color-danger-bg);padding:1.125rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;border:2px solid #0A0A0A;box-shadow:2px 2px 0 #0A0A0A;'
                         : 'background:var(--color-teal-bg);padding:1.125rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;border:2px solid #0A0A0A;box-shadow:2px 2px 0 #0A0A0A;'">
                <div>
                    <span class="stat-block-label" style="display:block;margin-bottom:0.5rem;">01 — SENTIMENT LEVEL 1</span>
                    <h3 style="font-size:1.25rem;font-weight:900;letter-spacing:-0.03em;margin:0 0 0.375rem;color:#0A0A0A;"
                        x-text="result && result.label_lvl1 === 'hate_speech' ? 'HATE SPEECH' : 'NON-HATE (SAFE)'">
                    </h3>
                    <template x-if="result && result.label_lvl1 === 'hate_speech'">
                        <span class="badge badge-hate" style="font-size:0.5625rem;">DETECTED: HATE SPEECH</span>
                    </template>
                    <template x-if="result && result.label_lvl1 !== 'hate_speech'">
                        <span class="badge badge-safe" style="font-size:0.5625rem;">VERIFIED: SAFE / NEUTRAL</span>
                    </template>
                </div>
                <div style="border-top:1px solid rgba(10,10,10,0.2);padding-top:0.625rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;font-family:var(--font-mono);font-size:0.6875rem;margin-bottom:0.375rem;">
                        <span style="color:var(--color-text-muted);">CONFIDENCE:</span>
                        <strong style="font-size:1rem;color:#0A0A0A;"
                                x-text="result ? (result.confidence_lvl1 * 100).toFixed(1) + '%' : '—'"></strong>
                    </div>
                    <div class="progress-track-sharp">
                        <div class="progress-fill-sharp"
                             :style="'width:' + (result ? (result.confidence_lvl1 * 100) : 0) + '%;background:' + (result && result.label_lvl1 === 'hate_speech' ? '#0A0A0A' : 'var(--color-teal)')">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 02: Level 2 Taxonomy --}}
            <div style="background:#FFFFFF;padding:1.125rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;border:2px solid #0A0A0A;box-shadow:2px 2px 0 #0A0A0A;">
                <div>
                    <span class="stat-block-label" style="display:block;margin-bottom:0.5rem;">02 — TAXONOMY LEVEL 2</span>
                    <h3 style="font-size:1.125rem;font-weight:900;letter-spacing:-0.025em;margin:0 0 0.375rem;color:var(--color-primary);"
                        x-text="result ? formatLabel(result.label_lvl2) : '—'">
                    </h3>
                    <span class="badge badge-mono" style="font-size:0.5625rem;">6 SUBCATEGORIES</span>
                </div>
                <div style="border-top:1px solid var(--color-border-subtle);padding-top:0.625rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;font-family:var(--font-mono);font-size:0.6875rem;margin-bottom:0.375rem;">
                        <span style="color:var(--color-text-muted);">SUBTYPE CONFIDENCE:</span>
                        <strong style="font-size:1rem;color:#0A0A0A;"
                                x-text="result && result.confidence_lvl2 ? (result.confidence_lvl2 * 100).toFixed(1) + '%' : '—'"></strong>
                    </div>
                    <div class="progress-track-sharp">
                        <div class="progress-fill-sharp"
                             :style="'width:' + (result && result.confidence_lvl2 ? (result.confidence_lvl2 * 100) : 0) + '%;background:#0A0A0A;'">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 03: Kamusalay Preprocessing --}}
            <div style="background:var(--color-surface-2);padding:1.125rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;border:2px solid #0A0A0A;box-shadow:2px 2px 0 #0A0A0A;">
                <div>
                    <span class="stat-block-label" style="display:block;margin-bottom:0.5rem;">03 — KAMUSALAY PREPROCESSING</span>

                    <div style="background:#FFFFFF;border:1px solid var(--color-border-subtle);padding:0.5rem 0.625rem;margin-bottom:0.5rem;">
                        <div style="font-family:var(--font-mono);font-size:0.5625rem;color:var(--color-text-subtle);font-weight:700;margin-bottom:2px;">RAW INPUT:</div>
                        <p style="font-size:0.75rem;color:#0A0A0A;margin:0;line-height:1.5;font-family:var(--font-sans);word-break:break-word;"
                           x-text="inputText"></p>
                    </div>

                    <div style="background:var(--color-primary-bg);border:1px solid var(--color-primary);border-left:3px solid var(--color-primary);padding:0.5rem 0.625rem;">
                        <div style="font-family:var(--font-mono);font-size:0.5625rem;color:var(--color-primary);font-weight:700;margin-bottom:2px;">NORMALIZED:</div>
                        <p style="font-size:0.75rem;color:var(--color-primary);margin:0;line-height:1.5;font-family:var(--font-mono);word-break:break-word;font-weight:600;"
                           x-text="result ? result.clean_text : '—'"></p>
                    </div>
                </div>

                <div style="border-top:1px solid var(--color-border-subtle);padding-top:0.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.25rem;">
                    <span style="font-family:var(--font-mono);font-size:0.5625rem;color:var(--color-teal);font-weight:700;">15,167 DICTIONARY RULES</span>
                    <span class="badge badge-safe" style="font-size:0.5rem;">INDOBERT READY</span>
                </div>
            </div>

        </div>{{-- /3-cards --}}
    </div>{{-- /x-show result --}}


    {{-- Loading placeholder --}}
    <div x-show="loading" x-cloak
         style="border:2px solid #0A0A0A;box-shadow:3px 3px 0 #0A0A0A;background:#FFFFFF;padding:2.5rem 1.5rem;text-align:center;">
        <span class="telemetry-dot pulse" style="margin:0 auto;display:block;"></span>
        <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:var(--color-text-muted);margin:0.75rem 0 0;letter-spacing:0.06em;">
            PROCESSING INDOBERT TENSOR...
        </p>
    </div>

</div>{{-- /x-data --}}

@endsection
