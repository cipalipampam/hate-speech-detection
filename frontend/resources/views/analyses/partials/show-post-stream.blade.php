{{-- ── LEFT PANE: Filterable Postings Stream (70%) ── --}}
<div style="display:flex;flex-direction:column;gap:0.75rem;">

    {{-- Filter Toolbar --}}
    <div class="card" style="padding:0.75rem 1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
            <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;">
                POST STREAM (<span x-text="pagination.total || 0"></span>)
            </span>

            <div style="display:flex;align-items:center;gap:0.375rem;flex-wrap:wrap;">
                {{-- Search Input --}}
                <input type="text"
                       x-model.debounce.350ms="filters.search"
                       @input="fetchPosts(1)"
                       placeholder="Search text... (/)"
                       class="input"
                       style="width:140px;height:30px;font-size:0.75rem;padding:0.25rem 0.5rem;">

                {{-- L1 Filter --}}
                <select x-model="filters.label_lvl1" @change="fetchPosts(1)" class="input" style="width:auto;height:30px;font-size:0.75rem;padding:0 0.5rem;cursor:pointer;">
                    <option value="all">All L1</option>
                    <option value="hate_speech">Hate</option>
                    <option value="non_hate_speech">Safe</option>
                </select>

                {{-- L2 Filter --}}
                <select x-model="filters.label_lvl2" @change="fetchPosts(1)" class="input" style="width:auto;height:30px;font-size:0.75rem;padding:0 0.5rem;cursor:pointer;">
                    <option value="all">All L2</option>
                    <option value="delegitimasi_institusi">Delegitimasi Institusi</option>
                    <option value="dehumanisasi">Dehumanisasi</option>
                    <option value="ajakan_kekerasan">Ajakan Kekerasan</option>
                    <option value="hoaks_pemicu_kebencian">Hoaks Pemicu Kebencian</option>
                    <option value="kutukan_agama_personal">Kutukan Agama & Personal</option>
                    <option value="tidak_relevan">Non-Hate / Neutral</option>
                </select>

                {{-- Platform Filter --}}
                <select x-model="filters.platform" @change="fetchPosts(1)" class="input" style="width:auto;height:30px;font-size:0.75rem;padding:0 0.5rem;cursor:pointer;">
                    <option value="all">All 𝕏/⊙</option>
                    <option value="X">𝕏 Twitter</option>
                    <option value="Threads">⊙ Threads</option>
                </select>

                <button type="button" x-show="filters.search || filters.label_lvl1 !== 'all' || filters.label_lvl2 !== 'all' || filters.platform !== 'all'"
                        @click="resetFilters()" class="btn btn-ghost btn-sm" style="height:30px;padding:0 0.35rem;">✕</button>
            </div>
        </div>
    </div>

    {{-- Postings List --}}
    <div style="position:relative;display:flex;flex-direction:column;gap:0.625rem;">
        {{-- Skeleton Loading Overlay --}}
        <div x-show="loadingPosts" x-cloak style="position:absolute;inset:0;background:rgba(246,245,240,0.75);display:flex;align-items:center;justify-content:center;z-index:10;">
            <span class="badge badge-black">LOADING CORPUS...</span>
        </div>

        <template x-if="posts.length === 0 && !loadingPosts">
            <div class="card" style="padding:3rem 1rem;text-align:center;font-family:var(--font-mono);color:var(--color-text-muted);">
                [NO POSTS MATCH THE FILTER]
            </div>
        </template>

        <template x-for="(post, index) in posts" :key="post.id">
            <div class="card"
                 @click="selectedPost = post"
                 :style="selectedPost && selectedPost.id === post.id ? 'border: 2px solid #002FA7; box-shadow: 4px 4px 0 #002FA7;' : ''"
                 style="padding:1rem;cursor:pointer;transition:all 0.1s;">
                
                {{-- Post Meta Header --}}
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;font-family:var(--font-mono);font-size:0.6875rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <template x-if="post.platform?.toLowerCase() === 'x' || post.platform?.toLowerCase() === 'twitter'">
                            <span class="badge badge-black" style="font-size:0.625rem;">𝕏 TWITTER</span>
                        </template>
                        <template x-if="post.platform?.toLowerCase() === 'threads'">
                            <span class="badge badge-mono" style="font-size:0.625rem;">⊙ THREADS</span>
                        </template>
                        <span style="font-weight:700;color:#0A0A0A;" x-text="post.author_username ? '@' + post.author_username : '@anonim'"></span>
                        <span style="color:var(--color-text-muted);" x-text="post.post_date ? post.post_date.split('T')[0] : ''"></span>
                    </div>

                    <div style="display:flex;align-items:center;gap:0.375rem;">
                        <template x-if="post.classification?.label_lvl1 === 'hate_speech'">
                            <span class="badge badge-hate" style="font-size:0.625rem;">HATE SPEECH</span>
                        </template>
                        <template x-if="post.classification?.label_lvl1 !== 'hate_speech'">
                            <span class="badge badge-safe" style="font-size:0.625rem;">NON-HATE</span>
                        </template>
                        <span class="badge badge-mono" style="font-size:0.625rem;" x-text="formatLabel(post.classification?.label_lvl2)"></span>
                    </div>
                </div>

                {{-- Post Text Verbatim --}}
                <p style="font-size:0.875rem;line-height:1.55;color:#0A0A0A;margin:0 0 0.5rem;font-weight:500;"
                   x-text="post.classification?.raw_content || '—'"></p>

                {{-- Confidence & Footer --}}
                <div style="display:flex;align-items:center;justify-content:space-between;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);border-top:1px dashed var(--color-border-subtle);padding-top:0.375rem;">
                    <span>CONFIDENCE: <strong style="color:#0A0A0A;" x-text="post.classification ? (post.classification.confidence_lvl1 * 100).toFixed(1) + '%' : '—'"></strong></span>
                    <span style="color:var(--color-primary);font-weight:700;">CLICK TO INSPECT [→]</span>
                </div>
            </div>
        </template>
    </div>

    {{-- Pagination Controls --}}
    <div class="card" style="padding:0.75rem 1rem;display:flex;align-items:center;justify-content:space-between;font-family:var(--font-mono);font-size:0.75rem;flex-wrap:wrap;gap:0.5rem;">
        <span>PAGE <strong x-text="pagination.current_page || 1"></strong> OF <strong x-text="pagination.last_page || 1"></strong></span>

        <div style="display:flex;gap:0.375rem;">
            <button type="button" :disabled="!pagination.prev_page_url || loadingPosts"
                    @click="fetchPosts(pagination.current_page - 1)" class="btn btn-outline btn-sm"
                    :style="(!pagination.prev_page_url || loadingPosts) ? 'opacity:0.4;cursor:not-allowed;' : ''">
                ← PREV
            </button>
            <button type="button" :disabled="!pagination.next_page_url || loadingPosts"
                    @click="fetchPosts(pagination.current_page + 1)" class="btn btn-outline btn-sm"
                    :style="(!pagination.next_page_url || loadingPosts) ? 'opacity:0.4;cursor:not-allowed;' : ''">
                NEXT →
            </button>
        </div>
    </div>

</div>

