@extends('layouts.app')

@section('title', '§ 02.1 New Analysis')

@section('breadcrumb')
<a href="{{ route('analyses.index') }}" style="color:var(--color-text-muted);text-decoration:none;">§ 02.0 ANALYSIS HISTORY</a>
<span>/</span>
<span style="color:#0A0A0A;">+ NEW ANALYSIS</span>
@endsection

@section('content')

<div style="max-width:820px;margin:0 auto;">

    {{-- Monograph Section Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:1.75rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
            <span class="badge badge-black">SECTION § 02.1</span>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">ANALYSIS PIPELINE CONFIGURATION</span>
        </div>
        <h1 style="font-size:2rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0;line-height:1.1;">
            NEW MULTI-PLATFORM ANALYSIS
        </h1>
        <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
            Configure social media collection parameters for X and Threads, then run hierarchical IndoBERT inference.
        </p>
    </div>

    @if($errors->any())
    <div style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;padding:0.875rem 1rem;margin-bottom:1.5rem;">
        <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin:0 0 0.25rem;">VALIDATION FAILED:</p>
        <ul style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;padding-left:1.25rem;">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- JSON Island: data server awal untuk halaman buat analisis baru --}}
    <script id="analysis-create-data" type="application/json">
        {!! json_encode([
            'keywords' => old('keywords', []),
            'platform' => old('platform', 'both'),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>

    <form method="POST" action="{{ route('analyses.store') }}" x-data="analysisForm()" id="form-analysis">
        @csrf

        {{-- ── Card 1: Research Identity & Keywords ── --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">
            <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.625rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;">
                <span class="stat-block-label">01. ANALYSIS IDENTITY & KEYWORDS</span>
                <span class="badge badge-mono">REQUIRED</span>
            </div>

            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                {{-- Analysis Title --}}
                <div>
                    <label class="label" for="title">Analysis Title / Research Topic <span style="color:var(--color-crimson);">*</span></label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}"
                           placeholder="e.g. Hate Speech Analysis of Public Policy Discourse"
                           class="input {{ $errors->has('title') ? 'error' : '' }}"
                           style="height:42px;"
                           maxlength="255" required>
                    <p style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);margin-top:0.35rem;">
                        The research query identity stored with the analysis record and CSV export.
                    </p>
                </div>

                {{-- Keyword Input & Tokens --}}
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.375rem;">
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <label class="label" style="margin:0;">Analysis Keywords / Topics <span style="color:var(--color-crimson);">*</span></label>
                            <span x-show="keywords.length >= 10" class="badge badge-hate" style="font-size:0.625rem;" x-cloak>10-KEYWORD LIMIT REACHED</span>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;"
                              :style="keywords.length >= 10 ? 'color:var(--color-crimson)' : 'color:var(--color-text-muted)'"
                              x-text="keywords.length + '/10 KEYWORDS'"></span>
                    </div>

                    {{-- Search Input Group --}}
                    <div style="display:flex;gap:0.5rem;align-items:stretch;">
                        <input x-ref="kwinput"
                               type="text"
                               :disabled="keywords.length >= 10"
                               placeholder="Type a keyword or phrase, then press Enter to add..."
                               class="input"
                               style="height:42px;flex:1;"
                               @keydown.enter.prevent="if(keywords.length < 10) addKeyword($refs.kwinput)"
                               @keydown.comma.prevent="if(keywords.length < 10) addKeyword($refs.kwinput)">
                        <button type="button"
                                @click="addKeyword($refs.kwinput)"
                                :disabled="keywords.length >= 10"
                                :style="keywords.length >= 10 ? 'opacity:0.4;cursor:not-allowed;' : ''"
                                class="btn btn-primary"
                                style="height:42px;padding:0 1.25rem;">
                            + ADD
                        </button>
                    </div>

                    {{-- Tag Container Box --}}
                    <div style="margin-top:0.625rem;background:var(--color-surface-2);border:1px solid #0A0A0A;padding:0.75rem;min-height:48px;display:flex;flex-wrap:wrap;align-items:center;gap:0.375rem;">
                        <template x-if="keywords.length === 0">
                            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);margin:0 auto;">
                                [NO KEYWORDS — AT LEAST 1 KEYWORD IS REQUIRED]
                            </span>
                        </template>

                        <template x-for="(kw, i) in keywords" :key="i">
                            <div style="display:inline-flex;align-items:center;gap:0.35rem;background:#FFFFFF;border:1px solid #0A0A0A;padding:0.25rem 0.5rem 0.25rem 0.625rem;box-shadow:1px 1px 0 #0A0A0A;">
                                <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;" x-text="kw"></span>
                                <button type="button"
                                        @click="removeKeyword(i)"
                                        style="background:none;border:none;cursor:pointer;font-family:var(--font-mono);font-weight:900;font-size:0.875rem;color:var(--color-crimson);padding:0 2px;"
                                        title="Remove keyword">
                                    ×
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Quick Preset Suggestions --}}
                    <div style="margin-top:0.625rem;display:flex;align-items:center;gap:0.375rem;flex-wrap:wrap;">
                        <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:700;color:var(--color-text-muted);">Quick presets:</span>
                        @foreach(['#SemuaBisaKena', '#SemuaBisaJadiKorban', '#KUHAPCacat', '#TolakRKUHAP', '#PeringatanDarurat', 'RKUHAP', 'KUHAP Baru', 'Revisi KUHAP', 'Undang-Undang KUHAP', 'Keadilan Restoratif'] as $suggest)
                        <button type="button"
                                @click="if(!keywords.includes('{{ $suggest }}') && keywords.length < 10) keywords.push('{{ $suggest }}')"
                                :disabled="keywords.length >= 10"
                                :style="keywords.length >= 10 ? 'opacity:0.4;cursor:not-allowed;' : ''"
                                class="btn btn-outline btn-sm"
                                style="padding:1px 6px;font-size:0.6875rem;height:24px;">
                            + {{ $suggest }}
                        </button>
                        @endforeach
                    </div>

                    <template x-for="kw in keywords">
                        <input type="hidden" name="keywords[]" :value="kw">
                    </template>
                </div>
            </div>
        </div>

        {{-- ── Card 2: Platform Target & Parameter ── --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">
            <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.625rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;">
                <span class="stat-block-label">02. PLATFORM & COLLECTION DEPTH</span>
                <span class="badge badge-mono">CRAWLER</span>
            </div>

            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                {{-- Typographic Platform Selector --}}
                <div>
                    <label class="label" style="margin-bottom:0.625rem;">Target Social Platforms <span style="color:var(--color-crimson);">*</span></label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(240px, 100%), 1fr));gap:1rem;align-items:stretch;">

                        {{-- Twitter X --}}
                        <label style="cursor:pointer;display:flex;flex-direction:column;margin:0;" @click="platform = 'x'">
                            <input type="radio" name="platform" value="x" {{ old('platform', 'both') === 'x' ? 'checked' : '' }} class="sr-only" x-model="platform">
                            <div class="card"
                                 :style="platform === 'x' ? 'border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;background:#FFFFFF;' : 'border:1px solid var(--color-border);box-shadow:2px 2px 0 rgba(10,10,10,0.15);background:var(--color-surface-2);'"
                                 style="padding:1.25rem;height:100%;display:flex;flex-direction:column;justify-content:space-between;gap:1rem;transition:all 0.15s ease;">
                                <div>
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.625rem;">
                                        <span style="font-family:var(--font-mono);font-size:0.875rem;font-weight:900;color:#0A0A0A;letter-spacing:0.02em;">
                                            𝕏 TWITTER
                                        </span>
                                        <template x-if="platform === 'x'">
                                            <span class="badge badge-black" style="font-size:0.625rem;">✓ SELECTED</span>
                                        </template>
                                        <template x-if="platform !== 'x'">
                                            <span class="badge badge-mono" style="font-size:0.625rem;opacity:0.6;">SELECT</span>
                                        </template>
                                    </div>
                                    <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.5;">
                                        Collect public tweets, threaded discussions, and direct opinions from 𝕏.
                                    </p>
                                </div>
                                <div style="border-top:1px solid var(--color-border);padding-top:0.625rem;display:flex;justify-content:space-between;align-items:center;">
                                    <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">PLAYWRIGHT ENGINE</span>
                                    <span class="badge badge-mono" style="font-size:0.625rem;">TWEET / POST</span>
                                </div>
                            </div>
                        </label>

                        {{-- Threads --}}
                        <label style="cursor:pointer;display:flex;flex-direction:column;margin:0;" @click="platform = 'threads'">
                            <input type="radio" name="platform" value="threads" {{ old('platform', 'both') === 'threads' ? 'checked' : '' }} class="sr-only" x-model="platform">
                            <div class="card"
                                 :style="platform === 'threads' ? 'border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;background:#FFFFFF;' : 'border:1px solid var(--color-border);box-shadow:2px 2px 0 rgba(10,10,10,0.15);background:var(--color-surface-2);'"
                                 style="padding:1.25rem;height:100%;display:flex;flex-direction:column;justify-content:space-between;gap:1rem;transition:all 0.15s ease;">
                                <div>
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.625rem;">
                                        <span style="font-family:var(--font-mono);font-size:0.875rem;font-weight:900;color:#0A0A0A;letter-spacing:0.02em;">
                                            ⊙ THREADS
                                        </span>
                                        <template x-if="platform === 'threads'">
                                            <span class="badge badge-black" style="font-size:0.625rem;">✓ SELECTED</span>
                                        </template>
                                        <template x-if="platform !== 'threads'">
                                            <span class="badge badge-mono" style="font-size:0.625rem;opacity:0.6;">SELECT</span>
                                        </template>
                                    </div>
                                    <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.5;">
                                        Collect long-form posts, comments, and public communities from Meta Threads.
                                    </p>
                                </div>
                                <div style="border-top:1px solid var(--color-border);padding-top:0.625rem;display:flex;justify-content:space-between;align-items:center;">
                                    <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">PLAYWRIGHT ENGINE</span>
                                    <span class="badge badge-mono" style="font-size:0.625rem;">THREAD / POST</span>
                                </div>
                            </div>
                        </label>

                        {{-- Dual Platform --}}
                        <label style="cursor:pointer;display:flex;flex-direction:column;margin:0;" @click="platform = 'both'">
                            <input type="radio" name="platform" value="both" {{ old('platform', 'both') === 'both' ? 'checked' : '' }} class="sr-only" x-model="platform">
                            <div class="card"
                                 :style="platform === 'both' ? 'border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;background:#FFFFFF;' : 'border:1px solid var(--color-border);box-shadow:2px 2px 0 rgba(10,10,10,0.15);background:var(--color-surface-2);'"
                                 style="padding:1.25rem;height:100%;display:flex;flex-direction:column;justify-content:space-between;gap:1rem;transition:all 0.15s ease;">
                                <div>
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.625rem;">
                                        <span style="font-family:var(--font-mono);font-size:0.875rem;font-weight:900;color:#0A0A0A;letter-spacing:0.02em;">
                                            BOTH PLATFORMS
                                        </span>
                                        <template x-if="platform === 'both'">
                                            <span class="badge badge-black" style="font-size:0.625rem;">✓ SELECTED</span>
                                        </template>
                                        <template x-if="platform !== 'both'">
                                            <span class="badge badge-mono" style="font-size:0.625rem;opacity:0.6;">SELECT</span>
                                        </template>
                                    </div>
                                    <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.5;">
                                        Crawl 𝕏 and Threads in parallel for cross-platform sentiment comparison.
                                    </p>
                                </div>
                                <div style="border-top:1px solid var(--color-border);padding-top:0.625rem;display:flex;justify-content:space-between;align-items:center;">
                                    <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-primary);font-weight:700;">PARALEL WORKER</span>
                                    <span class="badge badge-mono" style="font-size:0.625rem;">DUAL / 𝕏 + ⊙</span>
                                </div>
                            </div>
                        </label>

                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    {{-- Search Mode --}}
                    <div>
                        <label class="label" for="search_mode">Search Mode</label>
                        <select id="search_mode" name="search_mode" class="input" style="height:42px;cursor:pointer;">
                            <option value="latest" {{ old('search_mode','latest') === 'latest' ? 'selected' : '' }}>LATEST (Newest)</option>
                            <option value="top" {{ old('search_mode') === 'top' ? 'selected' : '' }}>TOP (Highest Engagement)</option>
                        </select>
                    </div>

                    {{-- Max Links --}}
                    <div>
                        <label class="label" for="max_links">Post Limit per Keyword (5–500)</label>
                        <input id="max_links" type="number" name="max_links" value="{{ old('max_links', 100) }}"
                               min="5" max="500" class="input" style="height:42px;">
                    </div>
                </div>

                {{-- Max Scroll Steps --}}
                <div>
                        <label class="label" for="max_scroll_steps">URL Search Scroll Limit per Keyword (50–5000)</label>
                    <input id="max_scroll_steps" type="number" name="max_scroll_steps" value="{{ old('max_scroll_steps', 300) }}"
                           min="50" max="5000" class="input" style="height:42px;">
                    <p style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);margin-top:0.35rem;">
                        Number of Playwright timeline scrolls used to find relevant posts.
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Card 3: Opsi Mesin Playwright ── --}}
        <div class="card" style="padding:1rem 1.5rem;margin-bottom:2rem;">
            <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;">
                {{-- Checkbox headless: checked by default saat kunjungan pertama.
                     Saat form gagal validasi, ikuti nilai yang benar-benar disubmit user.
                     old('headless', '1') TIDAK bisa dipakai karena Arr::get() mengembalikan
                     default '1' bahkan ketika checkbox tidak dicentang (key hilang dari POST). --}}
                <input type="checkbox" name="headless" value="1"
                       {{ !$errors->any() || old('headless') ? 'checked' : '' }}
                       style="width:16px;height:16px;accent-color:#0A0A0A;">
                <span style="font-family:var(--font-mono);font-size:0.8125rem;font-weight:700;color:#0A0A0A;">
                    [X] JALANKAN DALAM MODE HEADLESS BROWSER (BACKGROUND SERVICE)
                </span>
            </label>
        </div>

        {{-- Actions --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding-top:1rem;border-top:1px solid var(--color-border);">
            <a href="{{ route('analyses.index') }}" class="btn btn-outline">← CANCEL</a>
            <button type="submit" class="btn btn-primary btn-lg"
                    :disabled="keywords.length === 0"
                    :style="keywords.length === 0 ? 'opacity:0.4;cursor:not-allowed;' : ''">
                <span>↵ RUN ANALYSIS PIPELINE</span>
            </button>
        </div>

    </form>
</div>

@endsection
