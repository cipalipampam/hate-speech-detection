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

                {{-- Both Platforms --}}
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
                            <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-primary);font-weight:700;">PARALLEL WORKER</span>
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

