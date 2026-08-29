@extends('layouts.app')

@section('title', 'Analisis Baru')

@section('breadcrumb')
<div style="display:flex;align-items:center;gap:0.5rem;">
    <a href="{{ route('analyses.index') }}" style="font-size:0.875rem;color:var(--color-text-muted);font-weight:500;text-decoration:none;"
       onmouseover="this.style.color='var(--color-primary)'" onmouseout="this.style.color='var(--color-text-muted)'">Analisis</a>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-subtle)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">Baru</span>
</div>
@endsection

@section('content')

<div style="max-width:760px;margin:0 auto;">

    <div class="page-header">
        <h1 class="page-title">Analisis Baru</h1>
        <p class="page-subtitle">Konfigurasikan parameter scraping dan jalankan pipeline deteksi hate speech multi-platform.</p>
    </div>

    @if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1.25rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div>@foreach($errors->all() as $e)<p style="font-size:0.875rem;">{{ $e }}</p>@endforeach</div>
    </div>
    @endif

    <form method="POST" action="{{ route('analyses.store') }}" x-data="analysisForm()" id="form-analysis">
        @csrf

        {{-- ── Card 1: Identitas Sesi ── --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1.25rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--color-border);">
                <div style="width:36px;height:36px;border-radius:0.625rem;background:var(--color-primary-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div>
                    <p style="font-size:0.9375rem;font-weight:700;color:var(--color-navy);">Identitas Sesi</p>
                    <p style="font-size:0.8rem;color:var(--color-text-muted);">Beri judul dan kata kunci topik yang ingin dianalisis.</p>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                {{-- Judul Sesi --}}
                <div>
                    <label class="label" for="title">Judul Sesi Analisis <span style="color:var(--color-danger);">*</span></label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}"
                           placeholder="cth: Analisis Sentimen RUU Pilkada Agustus 2026"
                           class="input {{ $errors->has('title') ? 'error' : '' }}"
                           style="height:44px;"
                           maxlength="255" required>
                    <p style="font-size:0.75rem;color:var(--color-text-muted);margin-top:0.375rem;">Nama deskriptif untuk memudahkan pencarian riwayat nanti.</p>
                </div>

                {{-- Kata Kunci (Desain Modern: Input Group + Tag Chips + Quick Suggestion) --}}
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.375rem;">
                        <label class="label" style="margin:0;">Kata Kunci Pencarian <span style="color:var(--color-danger);">*</span></label>
                        <span style="font-size:0.75rem;font-weight:700;" :style="keywords.length >= 10 ? 'color:var(--color-danger)' : 'color:var(--color-text-muted)'" x-text="keywords.length + '/10 kata kunci'"></span>
                    </div>

                    {{-- Search Input Group --}}
                    <div style="display:flex;gap:0.5rem;align-items:stretch;">
                        <div style="position:relative;flex:1;">
                            <div style="position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);color:var(--color-text-muted);display:flex;align-items:center;pointer-events:none;">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            </div>
                            <input x-ref="kwinput"
                                   type="text"
                                   placeholder="Ketik kata kunci atau tagar (lalu tekan Enter atau tombol Tambah)..."
                                   class="input"
                                   style="padding-left:2.5rem;padding-right:0.875rem;height:44px;"
                                   @keydown.enter.prevent="addKeyword($refs.kwinput)"
                                   @keydown.comma.prevent="addKeyword($refs.kwinput)">
                        </div>
                        <button type="button"
                                @click="addKeyword($refs.kwinput)"
                                class="btn btn-navy"
                                style="height:44px;padding:0 1.25rem;gap:0.375rem;flex-shrink:0;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <span>Tambah</span>
                        </button>
                    </div>

                    {{-- Tag Container Box --}}
                    <div style="margin-top:0.75rem;background:var(--color-surface-2);border:1.5px dashed var(--color-border-2);border-radius:0.875rem;padding:0.75rem;min-height:52px;display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;">
                        <template x-if="keywords.length === 0">
                            <p style="font-size:0.8125rem;color:var(--color-text-muted);font-style:italic;margin:0 auto;display:flex;align-items:center;gap:0.375rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                Belum ada kata kunci ditambahkan. Masukkan minimal 1 kata kunci.
                            </p>
                        </template>

                        <template x-for="(kw, i) in keywords" :key="i">
                            <div style="display:inline-flex;align-items:center;gap:0.4rem;background:#FFFFFF;border:1.5px solid var(--color-border);padding:0.35rem 0.5rem 0.35rem 0.75rem;border-radius:0.625rem;box-shadow:0 1px 3px rgba(0,0,0,0.04);transition:all 0.15s;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                <span style="font-size:0.8125rem;font-weight:700;color:var(--color-navy);" x-text="kw"></span>
                                <button type="button"
                                        @click="removeKeyword(i)"
                                        style="width:20px;height:20px;border-radius:50%;background:var(--color-surface-2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--color-text-muted);transition:all 0.15s;margin-left:2px;"
                                        onmouseover="this.style.background='var(--color-danger-bg)';this.style.color='var(--color-danger)'"
                                        onmouseout="this.style.background='var(--color-surface-2)';this.style.color='var(--color-text-muted)'"
                                        title="Hapus kata kunci">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Rekomendasi Cepat / Quick Suggestions --}}
                    <div style="margin-top:0.625rem;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                        <span style="font-size:0.75rem;font-weight:600;color:var(--color-text-muted);">Saran Cepat:</span>
                        @foreach(['Pilkada 2026', 'Pemerintah', 'Korupsi', 'DPR', 'Kebijakan'] as $suggest)
                        <button type="button"
                                @click="if(!keywords.includes('{{ $suggest }}') && keywords.length < 10) keywords.push('{{ $suggest }}')"
                                style="font-size:0.75rem;font-weight:600;color:var(--color-text-body);background:#FFFFFF;border:1.5px solid var(--color-border);padding:3px 10px;border-radius:999px;cursor:pointer;transition:all 0.15s;display:inline-flex;align-items:center;gap:3px;"
                                onmouseover="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)';this.style.background='var(--color-primary-bg)'"
                                onmouseout="this.style.borderColor='var(--color-border)';this.style.color='var(--color-text-body)';this.style.background='#FFFFFF'">
                            <span>+</span> <span>{{ $suggest }}</span>
                        </button>
                        @endforeach
                    </div>

                    {{-- Hidden inputs for form submission --}}
                    <template x-for="kw in keywords">
                        <input type="hidden" name="keywords[]" :value="kw">
                    </template>
                </div>
            </div>
        </div>

        {{-- ── Card 2: Konfigurasi Scraping ── --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1.25rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--color-border);">
                <div style="width:36px;height:36px;border-radius:0.625rem;background:var(--color-secondary-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-secondary-dark)" stroke-width="2.5"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 0-2-2V9m0 0h18"/></svg>
                </div>
                <div>
                    <p style="font-size:0.9375rem;font-weight:700;color:var(--color-navy);">Konfigurasi Scraping</p>
                    <p style="font-size:0.8rem;color:var(--color-text-muted);">Tentukan platform target dan parameter pengambilan data.</p>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:1.25rem;">

                {{-- Platform Target (3 Rich Interactive Cards) --}}
                <div>
                    <label class="label" style="margin-bottom:0.625rem;">Platform Target <span style="color:var(--color-danger);">*</span></label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:0.875rem;">

                        {{-- Option 1: X (Twitter) --}}
                        <label style="cursor:pointer;position:relative;display:block;" @click="platform = 'x'">
                            <input type="radio" name="platform" value="x" {{ old('platform', 'both') === 'x' ? 'checked' : '' }} class="sr-only" x-model="platform">
                            <div style="border:2px solid;border-radius:1rem;padding:1.125rem 1rem;background:#FFFFFF;transition:all 0.2s;height:100%;display:flex;flex-direction:column;gap:0.75rem;position:relative;"
                                 :style="platform === 'x' ? 'border-color:#1DA1F2;background:#F4F9FD;box-shadow:0 4px 14px rgba(29,161,242,0.15);' : 'border-color:var(--color-border);'">
                                
                                <div style="display:flex;align-items:center;justify-content:space-between;">
                                    <div style="width:38px;height:38px;border-radius:0.75rem;background:#0F1419;display:flex;align-items:center;justify-content:center;color:#FFFFFF;flex-shrink:0;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                        </svg>
                                    </div>
                                    <div style="width:20px;height:20px;border-radius:50%;border:2px solid;display:flex;align-items:center;justify-content:center;transition:all 0.15s;"
                                         :style="platform === 'x' ? 'border-color:#1DA1F2;background:#1DA1F2;' : 'border-color:var(--color-border-2);background:#FFF;'">
                                        <svg x-show="platform === 'x'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#FFF" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                </div>

                                <div>
                                    <p style="font-size:0.9375rem;font-weight:800;color:var(--color-navy);line-height:1.2;">Twitter (𝕏)</p>
                                    <p style="font-size:0.75rem;color:var(--color-text-muted);margin-top:0.25rem;">Tweet publik & balasan</p>
                                </div>
                            </div>
                        </label>

                        {{-- Option 2: Threads --}}
                        <label style="cursor:pointer;position:relative;display:block;" @click="platform = 'threads'">
                            <input type="radio" name="platform" value="threads" {{ old('platform', 'both') === 'threads' ? 'checked' : '' }} class="sr-only" x-model="platform">
                            <div style="border:2px solid;border-radius:1rem;padding:1.125rem 1rem;background:#FFFFFF;transition:all 0.2s;height:100%;display:flex;flex-direction:column;gap:0.75rem;position:relative;"
                                 :style="platform === 'threads' ? 'border-color:#1E3A4C;background:#F2F6F8;box-shadow:0 4px 14px rgba(30,58,76,0.15);' : 'border-color:var(--color-border);'">
                                
                                <div style="display:flex;align-items:center;justify-content:space-between;">
                                    <div style="width:38px;height:38px;border-radius:0.75rem;background:#1E3A4C;display:flex;align-items:center;justify-content:center;color:#FFFFFF;flex-shrink:0;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>
                                        </svg>
                                    </div>
                                    <div style="width:20px;height:20px;border-radius:50%;border:2px solid;display:flex;align-items:center;justify-content:center;transition:all 0.15s;"
                                         :style="platform === 'threads' ? 'border-color:#1E3A4C;background:#1E3A4C;' : 'border-color:var(--color-border-2);background:#FFF;'">
                                        <svg x-show="platform === 'threads'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#FFF" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                </div>

                                <div>
                                    <p style="font-size:0.9375rem;font-weight:800;color:var(--color-navy);line-height:1.2;">Threads</p>
                                    <p style="font-size:0.75rem;color:var(--color-text-muted);margin-top:0.25rem;">Postingan & komentar Meta</p>
                                </div>
                            </div>
                        </label>

                        {{-- Option 3: Both (Keduanya / Dual) --}}
                        <label style="cursor:pointer;position:relative;display:block;" @click="platform = 'both'">
                            <input type="radio" name="platform" value="both" {{ old('platform', 'both') === 'both' ? 'checked' : '' }} class="sr-only" x-model="platform">
                            <div style="border:2px solid;border-radius:1rem;padding:1.125rem 1rem;background:#FFFFFF;transition:all 0.2s;height:100%;display:flex;flex-direction:column;gap:0.75rem;position:relative;"
                                 :style="platform === 'both' ? 'border-color:var(--color-primary);background:var(--color-primary-bg);box-shadow:0 4px 16px rgba(231,111,81,0.2);' : 'border-color:var(--color-border);'">
                                
                                {{-- Badge Rekomendasi --}}
                                <div style="position:absolute;top:-9px;right:12px;background:linear-gradient(135deg,var(--color-primary),var(--color-secondary));color:#FFF;font-size:0.625rem;font-weight:800;letter-spacing:0.04em;text-transform:uppercase;padding:2px 8px;border-radius:999px;">
                                    Rekomendasi
                                </div>

                                <div style="display:flex;align-items:center;justify-content:space-between;">
                                    <div style="width:38px;height:38px;border-radius:0.75rem;background:linear-gradient(135deg,var(--color-primary),var(--color-secondary));display:flex;align-items:center;justify-content:center;color:#FFFFFF;flex-shrink:0;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>
                                        </svg>
                                    </div>
                                    <div style="width:20px;height:20px;border-radius:50%;border:2px solid;display:flex;align-items:center;justify-content:center;transition:all 0.15s;"
                                         :style="platform === 'both' ? 'border-color:var(--color-primary);background:var(--color-primary);' : 'border-color:var(--color-border-2);background:#FFF;'">
                                        <svg x-show="platform === 'both'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#FFF" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                </div>

                                <div>
                                    <p style="font-size:0.9375rem;font-weight:800;color:var(--color-navy);line-height:1.2;">Keduanya (Dual)</p>
                                    <p style="font-size:0.75rem;color:var(--color-text-muted);margin-top:0.25rem;">Scraping X + Threads paralel</p>
                                </div>
                            </div>
                        </label>

                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    {{-- Mode Pencarian --}}
                    <div>
                        <label class="label" for="search_mode">Mode Pencarian</label>
                        <select id="search_mode" name="search_mode" class="input" style="height:44px;cursor:pointer;">
                            <option value="latest" {{ old('search_mode','latest') === 'latest' ? 'selected' : '' }}>🕐 Latest (Terbaru)</option>
                            <option value="top" {{ old('search_mode') === 'top' ? 'selected' : '' }}>🔥 Top (Populer)</option>
                        </select>
                    </div>

                    {{-- Max Links --}}
                    <div>
                        <label class="label" for="max_links">Jumlah Maksimum Link</label>
                        <input id="max_links" type="number" name="max_links" value="{{ old('max_links', 100) }}"
                               min="5" max="500" class="input" style="height:44px;">
                        <p style="font-size:0.75rem;color:var(--color-text-muted);margin-top:0.375rem;">Rentang: 5 – 500 postingan.</p>
                    </div>
                </div>

                {{-- Max Scroll Steps --}}
                <div>
                    <label class="label" for="max_scroll_steps">Batas Scroll Halaman</label>
                    <input id="max_scroll_steps" type="number" name="max_scroll_steps" value="{{ old('max_scroll_steps', 300) }}"
                           min="50" max="5000" class="input" style="height:44px;">
                    <p style="font-size:0.75rem;color:var(--color-text-muted);margin-top:0.375rem;">Berapa kali halaman media sosial di-scroll untuk menemukan postingan (50–5000).</p>
                </div>

            </div>
        </div>

        {{-- ── Card 3: Opsi Lanjutan ── --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1.75rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" @click="showAdvanced = !showAdvanced">
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:36px;height:36px;border-radius:0.625rem;background:#EEF3F8;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-navy)" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M5.34 18.66l-1.41 1.41M12 2v2M12 20v2M4.93 4.93l1.41 1.41M18.66 18.66l1.41 1.41M2 12h2M20 12h2"/></svg>
                    </div>
                    <div>
                        <p style="font-size:0.9375rem;font-weight:700;color:var(--color-navy);">Opsi Lanjutan</p>
                        <p style="font-size:0.8rem;color:var(--color-text-muted);">Headless browser dan pengaturan otomasi.</p>
                    </div>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2"
                     :style="showAdvanced ? 'transform:rotate(180deg)' : ''"
                     style="transition:transform 0.2s;flex-shrink:0;">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>

            <div x-show="showAdvanced" x-cloak x-transition style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--color-border);">
                <label style="display:flex;align-items:flex-start;gap:0.875rem;cursor:pointer;">
                    <input type="checkbox" name="headless" value="1" {{ old('headless', '1') ? 'checked' : '' }}
                           style="width:18px;height:18px;accent-color:var(--color-primary);margin-top:2px;flex-shrink:0;">
                    <div>
                        <p style="font-size:0.9rem;font-weight:600;color:var(--color-navy);">Mode Headless Browser</p>
                        <p style="font-size:0.8125rem;color:var(--color-text-muted);margin-top:2px;line-height:1.6;">Jalankan browser scraping di background tanpa membuka GUI (lebih efisien & hemat memori).</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- ── Submit Actions ── --}}
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;">
            <a href="{{ route('analyses.index') }}" class="btn btn-ghost">← Batal</a>
            <button type="submit" class="btn btn-primary btn-lg"
                    :disabled="keywords.length === 0"
                    :style="keywords.length === 0 ? 'opacity:0.55;cursor:not-allowed;' : ''">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Jalankan Analisis
            </button>
        </div>

    </form>
</div>

@endsection

@push('scripts')
<script>
function analysisForm() {
    return {
        keywords: {{ json_encode(old('keywords', [])) }},
        platform: '{{ old('platform', 'both') }}',
        showAdvanced: false,

        addKeyword(input) {
            if (!input) return;
            const val = input.value.trim().replace(/,$/, '').trim();
            if (val.length >= 2 && !this.keywords.includes(val) && this.keywords.length < 10) {
                this.keywords.push(val);
            }
            input.value = '';
            input.focus();
        },

        addSuggestedKeyword(text) {
            if (!this.keywords.includes(text) && this.keywords.length < 10) {
                this.keywords.push(text);
            }
        },

        removeKeyword(index) {
            this.keywords.splice(index, 1);
        }
    }
}
</script>
@endpush
