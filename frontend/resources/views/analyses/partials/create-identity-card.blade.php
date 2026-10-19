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

