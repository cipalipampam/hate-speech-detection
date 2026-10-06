@extends('layouts.app')

@section('title', '§ 03.0 Live Classifier')

@section('breadcrumb')
<span style="color:#0A0A0A;">§ 03.0 LIVE CLASSIFIER</span>
@endsection

@section('content')

{{-- JSON Island: data server awal untuk halaman klasifikasi langsung --}}
<script id="predict-server-data" type="application/json">
    {!! json_encode([
        'endpoint' => route('predict.classify'),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
</script>

<div style="max-width:880px;margin:0 auto;" x-data="liveClassifier()">

    {{-- Monograph Section Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1.25rem;margin-bottom:2rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                <span class="badge badge-black">SECTION § 03.0</span>
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">AI LANGUAGE LAB</span>
            </div>
            <h1 style="font-size:2.25rem;font-weight:900;letter-spacing:-0.035em;color:#0A0A0A;margin:0;line-height:1.1;">
                LIVE CLASSIFIER
            </h1>
            <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
                Run instant single-text classification with hierarchical IndoBERT and Kamusalay normalization.
            </p>
        </div>

        <div class="badge badge-mono">
            <span>MODEL: INDOBERT-BASE-UNCASED</span>
        </div>
    </div>

    {{-- ── Main Sandbox Card ── --}}
    <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">

        {{-- Sample Chips --}}
        <div style="margin-bottom:1.5rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.625rem;">
                <span class="stat-block-label" style="margin:0;">QUICK TEST PRESETS:</span>
                <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">CLICK TO LOAD SAMPLE TEXT</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                <button type="button" @click="inputText = 'wajarlah dia awal nya kan juga anggota freemason (yahudi).'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Hate-Baiting Hoax
                </button>
                <button type="button" @click="inputText = 'kerjaan dewan pengkhianat rakyat: ruu yg menguntungkan penguasa dikebut, ruu yg pro rakyat gak pernah beres.'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Institutional Delegitimization
                </button>
                <button type="button" @click="inputText = 'kapan sih anjing2 kena azab'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Dehumanization
                </button>
                <button type="button" @click="inputText = 'tembak mati dnk biar seru'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Incitement to Violence
                </button>
                <button type="button" @click="inputText = 'semoga semua pejabat yang dzalim dapet azab gara-gara menyengsarakan rakyat'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Religious & Personal Abuse
                </button>
                <button type="button" @click="inputText = 'langkah transparansi ini penting banget, dpr lanjutkan biar publik ikut terlibat'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-primary);font-weight:900;margin-right:0.25rem;">●</span> Non-Hate / Neutral
                </button>
            </div>
        </div>

        {{-- Text Input Area --}}
        <div style="margin-bottom:1rem;">
            <label class="label" for="predict-text">RAW TEXT INPUT (VERBATIM):</label>
            <textarea id="predict-text"
                      x-model="inputText"
                      placeholder="Type or paste Indonesian text here, including slang, abbreviations, or social media dialect..."
                      class="input"
                      rows="4"
                      style="resize:vertical;font-size:0.9375rem;line-height:1.6;font-family:var(--font-sans);"></textarea>
            
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.375rem;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">
                <span>PIPELINE: KAMUSALAY NORMALIZATION (15,167 TERMS) + REGEX FILTER</span>
                <div>
                    <span x-show="inputText.trim().length > 0 && inputText.trim().length < 4" style="color:var(--color-crimson);font-weight:700;margin-right:0.5rem;" x-cloak>
                        (MINIMUM 4 CHARACTERS)
                    </span>
                    <span>LENGTH: <strong style="color:#0A0A0A;" x-text="inputText.length"></strong> CHARACTERS</span>
                </div>
            </div>
        </div>

        {{-- Execute Button --}}
        <button type="button"
                @click="classify()"
                :disabled="loading || inputText.trim().length < 4"
                :style="(loading || inputText.trim().length < 4) ? 'opacity:0.4;cursor:not-allowed;' : ''"
                class="btn btn-primary btn-lg"
                style="width:100%;height:46px;">
            <span x-show="!loading">↵ RUN HIERARCHICAL INDOBERT</span>
            <span x-show="loading" x-cloak>PROCESSING INDOBERT TENSOR...</span>
        </button>

        {{-- Error Banner --}}
        <div x-show="errorMsg" x-cloak style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:3px 3px 0 #0A0A0A;padding:0.75rem 1rem;margin-top:1rem;">
            <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin:0 0 2px;">INFERENCE WARNING:</p>
            <p style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;" x-text="errorMsg"></p>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             CLASSIFICATION RESULT (SWISS MONOGRAPH DOSSIER)
        ════════════════════════════════════════════════════════════ --}}
        <div x-show="result" x-cloak style="margin-top:2rem;border-top:2px solid #0A0A0A;padding-top:1.5rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="badge badge-black">TENSOR INFERENCE RESULT</span>
                    <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:var(--color-text-muted);">TWO-STAGE INDOBERT</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="badge badge-safe" style="font-size:0.625rem;">STATUS: SUCCESS</span>
                    <span class="badge badge-mono" style="font-size:0.625rem;" x-text="'LATENCY: ' + latency + 'ms'"></span>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(320px, 100%), 1fr));gap:1.25rem;margin-bottom:1.5rem;">

                {{-- Level 1: Sentimen --}}
                <div class="card"
                     :style="result && result.label_lvl1 === 'hate_speech' ? 'border:2px solid #0A0A0A;border-top:5px solid var(--color-danger);box-shadow:3px 3px 0 #0A0A0A;background:#FFFFFF;' : 'border:2px solid #0A0A0A;border-top:5px solid var(--color-primary);box-shadow:3px 3px 0 #0A0A0A;background:#FFFFFF;'"
                     style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between;gap:1rem;">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                            <span class="stat-block-label">LEVEL 1 — SENTIMENT STATUS</span>
                            <template x-if="result && result.label_lvl1 === 'hate_speech'">
                                <span class="badge badge-hate" style="font-size:0.6875rem;">DETECTED: HATE SPEECH</span>
                            </template>
                            <template x-if="result && result.label_lvl1 !== 'hate_speech'">
                                <span class="badge badge-safe" style="font-size:0.6875rem;">VERIFIED: SAFE / NEUTRAL</span>
                            </template>
                        </div>

                        <h3 style="font-size:1.375rem;font-weight:900;letter-spacing:-0.03em;margin:0 0 0.5rem;color:#0A0A0A;"
                            x-text="result && result.label_lvl1 === 'hate_speech' ? 'Hate Speech' : 'Non-Hate (Safe / Neutral)'">
                        </h3>
                        <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.45;">
                            First-stage binary classification for screening hostile content.
                        </p>
                    </div>

                    <div style="border-top:1px solid var(--color-border-subtle);padding-top:0.75rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-family:var(--font-mono);font-size:0.75rem;margin-bottom:0.5rem;">
                            <span style="color:var(--color-text-muted);">CONFIDENCE:</span>
                            <strong style="font-size:1rem;color:#0A0A0A;" x-text="result ? (result.confidence_lvl1 * 100).toFixed(1) + '%' : '—'"></strong>
                        </div>

                        <div class="progress-track-sharp" style="height:8px;background:var(--color-surface-2);border:1px solid #0A0A0A;">
                            <div class="progress-fill-sharp"
                                 :style="'width:' + (result ? (result.confidence_lvl1 * 100) : 0) + '%;background:' + (result && result.label_lvl1 === 'hate_speech' ? 'var(--color-danger)' : 'var(--color-primary)')"></div>
                        </div>
                    </div>
                </div>

                {{-- Level 2: Sub-Kategori --}}
                <div class="card"
                     style="border:2px solid #0A0A0A;border-top:5px solid #0A0A0A;box-shadow:3px 3px 0 #0A0A0A;background:#FFFFFF;padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between;gap:1rem;">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                            <span class="stat-block-label">LEVEL 2 — SPECIFIC TAXONOMY</span>
                            <span class="badge badge-mono" style="font-size:0.6875rem;">6 SUBCATEGORIES</span>
                        </div>

                        <h3 style="font-size:1.375rem;font-weight:900;letter-spacing:-0.03em;margin:0 0 0.5rem;color:var(--color-primary);"
                            x-text="result ? formatLabel(result.label_lvl2) : '—'">
                        </h3>
                        <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.45;">
                            Second-stage multi-class grouping within the academic hate speech taxonomy.
                        </p>
                    </div>

                    <div style="border-top:1px solid var(--color-border-subtle);padding-top:0.75rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-family:var(--font-mono);font-size:0.75rem;margin-bottom:0.5rem;">
                            <span style="color:var(--color-text-muted);">SUBTYPE CONFIDENCE:</span>
                            <strong style="font-size:1rem;color:#0A0A0A;" x-text="result && result.confidence_lvl2 ? (result.confidence_lvl2 * 100).toFixed(1) + '%' : '—'"></strong>
                        </div>

                        <div class="progress-track-sharp" style="height:8px;background:var(--color-surface-2);border:1px solid #0A0A0A;">
                            <div class="progress-fill-sharp"
                                 :style="'width:' + (result && result.confidence_lvl2 ? (result.confidence_lvl2 * 100) : 0) + '%;background:#0A0A0A;'"></div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Normalization Pipeline Comparison --}}
            <div class="card" style="border:2px solid #0A0A0A;box-shadow:3px 3px 0 #0A0A0A;padding:1.25rem 1.5rem;margin-bottom:1.5rem;background:#FFFFFF;">
                <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--color-border);padding-bottom:0.75rem;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        <span class="stat-block-label" style="display:block;margin-bottom:0.15rem;">PREPROCESSING TRANSFORM · 15,167-TERM KAMUSALAY NORMALIZATION</span>
                        <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">Clean mentions and URLs, apply regex filters, and expand abbreviations and slang.</span>
                    </div>
                    <span class="badge badge-mono" style="font-size:0.625rem;">REGEX + KAMUSALAY</span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(280px, 100%), 1fr));gap:1rem;align-items:stretch;">
                    <div style="background:var(--color-surface-2);border:1px solid #0A0A0A;padding:1rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.5rem;">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                                <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;">[01] RAW TEXT</span>
                                <span class="badge badge-mono" style="font-size:0.5625rem;" x-text="inputText.length + ' CHARACTERS'"></span>
                            </div>
                            <p style="font-size:0.875rem;color:#0A0A0A;margin:0;line-height:1.6;font-family:var(--font-sans);word-break:break-word;" x-text="inputText"></p>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:0.625rem;color:var(--color-text-subtle);">USER VERBATIM INPUT</span>
                    </div>

                    <div style="background:#FFFFFF;border:1px solid #0A0A0A;padding:1rem;border-left:4px solid var(--color-primary);display:flex;flex-direction:column;justify-content:space-between;gap:0.5rem;">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                                <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;color:var(--color-primary);text-transform:uppercase;">[02] KAMUSALAY NORMALIZED TEXT</span>
                                <span class="badge badge-safe" style="font-size:0.5625rem;">INDOBERT READY</span>
                            </div>
                            <p style="font-size:0.875rem;color:#0A0A0A;margin:0;line-height:1.6;font-family:var(--font-mono);word-break:break-word;" x-text="result ? result.clean_text : '—'"></p>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:0.625rem;color:var(--color-primary);font-weight:700;">AUTOMATICALLY NORMALIZED</span>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

@endsection
