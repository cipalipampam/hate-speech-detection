{{-- ── RIGHT PANE: Deep-Dive Sticky Incident Inspector (30%) ── --}}
<div style="position:sticky;top:72px;">
    <div class="card" style="padding:1.25rem;">
        <div style="border-bottom:2px solid #0A0A0A;padding-bottom:0.625rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <span class="badge badge-black" style="font-size:0.625rem;">INSPECTOR § 02.2</span>
                <h3 style="font-size:1rem;font-weight:900;color:#0A0A0A;margin:2px 0 0;">Incident Detail</h3>
            </div>
            <template x-if="selectedPost">
                <span class="badge badge-mono" style="font-size:0.625rem;" x-text="'#POST-' + selectedPost.id"></span>
            </template>
        </div>

        <template x-if="!selectedPost">
            <div style="padding:2.5rem 1rem;text-align:center;font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">
                [SELECT A POST FROM THE LEFT TO INSPECT IT WITH THE XAI HIGHLIGHTER]
            </div>
        </template>

        <template x-if="selectedPost">
            <div style="display:flex;flex-direction:column;gap:1rem;">
                {{-- Post Meta --}}
                <div class="card-flat" style="padding:0.75rem;font-family:var(--font-mono);font-size:0.75rem;display:flex;flex-direction:column;gap:0.25rem;">
                    <div>ACCOUNT: <strong style="color:#0A0A0A;" x-text="selectedPost.author_username ? '@' + selectedPost.author_username : '@anonymous'"></strong></div>
                    <div>SOURCE: <span class="badge badge-mono" x-text="selectedPost.platform"></span></div>
                    <div x-show="selectedPost.source_url">
                        <a :href="selectedPost.source_url" target="_blank" style="color:var(--color-primary);text-decoration:none;font-weight:700;">
                            OPEN ORIGINAL URL [↗]
                        </a>
                    </div>
                </div>

                {{-- XAI Word Highlighter Section --}}
                <div>
                    <span class="stat-block-label">EXPLAINABLE AI (XAI) ATTENTION TOKENS:</span>
                    <div style="background:#FFFFFF;border:1px solid #0A0A0A;padding:0.875rem;margin-top:0.375rem;line-height:1.8;font-size:0.875rem;color:#0A0A0A;">
                        <div x-html="renderXaiHighlighted(selectedPost.classification?.raw_content || selectedPost.classification?.clean_content, selectedPost.classification?.label_lvl1)"></div>
                    </div>
                    <template x-if="selectedPost.classification?.label_lvl1 === 'hate_speech'">
                        <span style="font-family:var(--font-mono);font-size:0.65rem;color:var(--color-text-subtle);margin-top:4px;display:block;">
                            *Yellow tags indicate high-attention toxic trigger tokens identified by IndoBERT.
                        </span>
                    </template>
                    <template x-if="selectedPost.classification?.label_lvl1 !== 'hate_speech'">
                        <span style="font-family:var(--font-mono);font-size:0.65rem;color:var(--color-teal);font-weight:700;margin-top:4px;display:block;">
                            ✓ Safe Content Verified: No hostile trigger tokens detected in this post.
                        </span>
                    </template>
                </div>

                {{-- Hierarchical Gauges --}}
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <span class="stat-block-label">HIERARCHICAL MODEL SCORE BREAKDOWN:</span>
                    
                    {{-- L1 Sentimen --}}
                    <div style="border:1px solid #0A0A0A;padding:0.625rem;background:var(--color-surface-2);">
                        <div style="display:flex;justify-content:space-between;font-family:var(--font-mono);font-size:0.725rem;font-weight:700;margin-bottom:0.25rem;">
                            <span>LEVEL 1: SENTIMENT</span>
                            <span x-text="selectedPost.classification ? (selectedPost.classification.confidence_lvl1 * 100).toFixed(1) + '%' : '—'"></span>
                        </div>
                        <div style="height:6px;background:#D4D3CB;border:1px solid #0A0A0A;overflow:hidden;">
                            <div :style="'width:' + (selectedPost.classification?.confidence_lvl1 * 100) + '%;background:' + (selectedPost.classification?.label_lvl1 === 'hate_speech' ? 'var(--color-danger)' : 'var(--color-primary)')" style="height:100%;"></div>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin-top:0.25rem;display:block;"
                              x-text="selectedPost.classification?.label_lvl1 === 'hate_speech' ? 'HATE SPEECH' : 'NON-HATE CONTENT'"></span>
                    </div>

                    {{-- L2 Sub-Kategori --}}
                    <div style="border:1px solid #0A0A0A;padding:0.625rem;background:#FFFFFF;">
                        <div style="display:flex;justify-content:space-between;font-family:var(--font-mono);font-size:0.725rem;font-weight:700;margin-bottom:0.25rem;">
                            <span>LEVEL 2: TAXONOMY</span>
                            <span x-text="selectedPost.classification ? (selectedPost.classification.confidence_lvl2 * 100).toFixed(1) + '%' : '—'"></span>
                        </div>
                        <div style="height:6px;background:#D4D3CB;border:1px solid #0A0A0A;overflow:hidden;">
                            <div :style="'width:' + (selectedPost.classification?.confidence_lvl2 * 100) + '%;background:#0A0A0A;'" style="height:100%;"></div>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:var(--color-primary);margin-top:0.25rem;display:block;"
                              x-text="formatLabel(selectedPost.classification?.label_lvl2)"></span>
                    </div>
                </div>

                {{-- Preprocessing Normalization Info --}}
                <div class="card-flat" style="padding:0.625rem;font-family:var(--font-mono);font-size:0.6875rem;">
                    <span style="font-weight:700;color:var(--color-text-muted);">KAMUSALAY CLEANED TEXT:</span>
                    <p style="margin:0.25rem 0 0;color:#0A0A0A;word-break:break-all;" x-text="selectedPost.classification?.clean_content || selectedPost.classification?.raw_content || '—'"></p>
                </div>

            </div>
        </template>
    </div>
</div>

