@extends('layouts.app')

@section('title', '§ 02.1 Investigasi Baru')

@section('breadcrumb')
<a href="{{ route('analyses.index') }}" style="color:var(--color-text-muted);text-decoration:none;">§ 02.0 RIWAYAT</a>
<span>/</span>
<span style="color:#0A0A0A;">+ INVESTIGASI BARU</span>
@endsection

@section('content')

<div style="max-width:820px;margin:0 auto;">

    {{-- Monograph Section Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:1.75rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
            <span class="badge badge-black">SEKSI § 02.1</span>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">KONFIGURASI PIPELINE RISET</span>
        </div>
        <h1 style="font-size:2rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0;line-height:1.1;">
            INVESTIGASI MULTI-PLATFORM BARU
        </h1>
        <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
            Atur parameter pengumpulan data media sosial (X & Threads) dan jalankan inferensi hierarkis IndoBERT.
        </p>
    </div>

    @if($errors->any())
    <div style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;padding:0.875rem 1rem;margin-bottom:1.5rem;">
        <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin:0 0 0.25rem;">VALIDASI GAGAL:</p>
        <ul style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;padding-left:1.25rem;">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('analyses.store') }}" x-data="analysisForm()" id="form-analysis">
        @csrf

        {{-- ── Card 1: Identitas Riset & Kata Kunci ── --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">
            <div style="border-bottom:1px solid #0A0A0A;padding-bottom:0.625rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;">
                <span class="stat-block-label">01. IDENTITAS DOSIR & KATA KUNCI</span>
                <span class="badge badge-mono">WAJIB</span>
            </div>

            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                {{-- Judul Sesi --}}
                <div>
                    <label class="label" for="title">Judul Investigasi / Topik Penelitian <span style="color:var(--color-crimson);">*</span></label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}"
                           placeholder="cth: Analisis Ujaran Kebencian Isu DPR & Kebijakan Publik"
                           class="input {{ $errors->has('title') ? 'error' : '' }}"
                           style="height:42px;"
                           maxlength="255" required>
                    <p style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);margin-top:0.35rem;">
                        Identitas kueri riset yang akan tercatat pada berkas dosir dan hasil ekspor CSV.
                    </p>
                </div>

                {{-- Kata Kunci Input & Tokens --}}
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.375rem;">
                        <label class="label" style="margin:0;">Kata Kunci / Topik Analisis <span style="color:var(--color-crimson);">*</span></label>
                        <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;"
                              :style="keywords.length >= 10 ? 'color:var(--color-crimson)' : 'color:var(--color-text-muted)'"
                              x-text="keywords.length + '/10 KATA KUNCI'"></span>
                    </div>

                    {{-- Search Input Group --}}
                    <div style="display:flex;gap:0.5rem;align-items:stretch;">
                        <input x-ref="kwinput"
                               type="text"
                               placeholder="Ketik kata kunci atau frasa (tekan Enter untuk menambah)..."
                               class="input"
                               style="height:42px;flex:1;"
                               @keydown.enter.prevent="addKeyword($refs.kwinput)"
                               @keydown.comma.prevent="addKeyword($refs.kwinput)">
                        <button type="button"
                                @click="addKeyword($refs.kwinput)"
                                class="btn btn-primary"
                                style="height:42px;padding:0 1.25rem;">
                            + TAMBAH
                        </button>
                    </div>

                    {{-- Tag Container Box --}}
                    <div style="margin-top:0.625rem;background:var(--color-surface-2);border:1px solid #0A0A0A;padding:0.75rem;min-height:48px;display:flex;flex-wrap:wrap;align-items:center;gap:0.375rem;">
                        <template x-if="keywords.length === 0">
                            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);margin:0 auto;">
                                [BELUM ADA KATA KUNCI — MINIMAL 1 KATA KUNCI DIBUTUHKAN]
                            </span>
                        </template>

                        <template x-for="(kw, i) in keywords" :key="i">
                            <div style="display:inline-flex;align-items:center;gap:0.35rem;background:#FFFFFF;border:1px solid #0A0A0A;padding:0.25rem 0.5rem 0.25rem 0.625rem;box-shadow:1px 1px 0 #0A0A0A;">
                                <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;" x-text="'#' + kw"></span>
                                <button type="button"
                                        @click="removeKeyword(i)"
                                        style="background:none;border:none;cursor:pointer;font-family:var(--font-mono);font-weight:900;font-size:0.875rem;color:var(--color-crimson);padding:0 2px;"
                                        title="Hapus token">
                                    ×
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Quick Preset Suggestions --}}
                    <div style="margin-top:0.625rem;display:flex;align-items:center;gap:0.375rem;flex-wrap:wrap;">
                        <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:700;color:var(--color-text-muted);">Saran Cepat:</span>
                        @foreach(['Pilkada', 'Pemerintah', 'Korupsi', 'DPR', 'Kebijakan', 'Politik'] as $suggest)
                        <button type="button"
                                @click="if(!keywords.includes('{{ $suggest }}') && keywords.length < 10) keywords.push('{{ $suggest }}')"
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
                <span class="stat-block-label">02. SELEKSI PLATFORM & KEDALAMAN</span>
                <span class="badge badge-mono">CRAWLER</span>
            </div>

            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                {{-- Typographic Platform Selector --}}
                <div>
                    <label class="label" style="margin-bottom:0.625rem;">Platform Media Sosial Target <span style="color:var(--color-crimson);">*</span></label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:1rem;align-items:stretch;">

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
                                            <span class="badge badge-black" style="font-size:0.625rem;">✓ TERPILIH</span>
                                        </template>
                                        <template x-if="platform !== 'x'">
                                            <span class="badge badge-mono" style="font-size:0.625rem;opacity:0.6;">PILIH</span>
                                        </template>
                                    </div>
                                    <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.5;">
                                        Ekstraksi tweet publik, diskursus thread, dan opini langsung dari platform 𝕏.
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
                                            <span class="badge badge-black" style="font-size:0.625rem;">✓ TERPILIH</span>
                                        </template>
                                        <template x-if="platform !== 'threads'">
                                            <span class="badge badge-mono" style="font-size:0.625rem;opacity:0.6;">PILIH</span>
                                        </template>
                                    </div>
                                    <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.5;">
                                        Ekstraksi postingan narasi panjang, komentar, dan komunitas publik dari Meta Threads.
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
                                            DUAL SIMULTAN
                                        </span>
                                        <template x-if="platform === 'both'">
                                            <span class="badge badge-black" style="font-size:0.625rem;">✓ TERPILIH</span>
                                        </template>
                                        <template x-if="platform !== 'both'">
                                            <span class="badge badge-mono" style="font-size:0.625rem;opacity:0.6;">PILIH</span>
                                        </template>
                                    </div>
                                    <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.5;">
                                        Crawling paralel 𝕏 + Threads untuk analisis komparatif sentimen lintas jejaring sosial.
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
                    {{-- Mode Pencarian --}}
                    <div>
                        <label class="label" for="search_mode">Mode Pencarian</label>
                        <select id="search_mode" name="search_mode" class="input" style="height:42px;cursor:pointer;">
                            <option value="latest" {{ old('search_mode','latest') === 'latest' ? 'selected' : '' }}>LATEST (Kronologis Terbaru)</option>
                            <option value="top" {{ old('search_mode') === 'top' ? 'selected' : '' }}>TOP (Interaksi Tertinggi)</option>
                        </select>
                    </div>

                    {{-- Max Links --}}
                    <div>
                        <label class="label" for="max_links">Batas Kuota Postingan (5–500)</label>
                        <input id="max_links" type="number" name="max_links" value="{{ old('max_links', 100) }}"
                               min="5" max="500" class="input" style="height:42px;">
                    </div>
                </div>

                {{-- Max Scroll Steps --}}
                <div>
                    <label class="label" for="max_scroll_steps">Batas Langkah Scroll Browser (50–5000)</label>
                    <input id="max_scroll_steps" type="number" name="max_scroll_steps" value="{{ old('max_scroll_steps', 300) }}"
                           min="50" max="5000" class="input" style="height:42px;">
                    <p style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);margin-top:0.35rem;">
                        Berapa kali browser Playwright mengulir timeline untuk menemukan postingan relevan.
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Card 3: Opsi Mesin Playwright ── --}}
        <div class="card" style="padding:1rem 1.5rem;margin-bottom:2rem;">
            <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;">
                <input type="checkbox" name="headless" value="1" {{ old('headless', '1') ? 'checked' : '' }}
                       style="width:16px;height:16px;accent-color:#0A0A0A;">
                <span style="font-family:var(--font-mono);font-size:0.8125rem;font-weight:700;color:#0A0A0A;">
                    [X] JALANKAN DALAM MODE HEADLESS BROWSER (BACKGROUND SERVICE)
                </span>
            </label>
        </div>

        {{-- Actions --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding-top:1rem;border-top:1px solid var(--color-border);">
            <a href="{{ route('analyses.index') }}" class="btn btn-outline">← BATAL</a>
            <button type="submit" class="btn btn-primary btn-lg"
                    :disabled="keywords.length === 0"
                    :style="keywords.length === 0 ? 'opacity:0.4;cursor:not-allowed;' : ''">
                <span>↵ EKSEKUSI PIPELINE ANALISIS</span>
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

        addKeyword(input) {
            if (!input) return;
            const val = input.value.trim().replace(/,$/, '').trim();
            if (val.length >= 2 && !this.keywords.includes(val) && this.keywords.length < 10) {
                this.keywords.push(val);
            }
            input.value = '';
            input.focus();
        },

        removeKeyword(index) {
            this.keywords.splice(index, 1);
        }
    }
}
</script>
@endpush
