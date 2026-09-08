@extends('layouts.app')

@section('title', '§ 03.0 Live Predict Sandbox')

@section('breadcrumb')
<span style="color:#0A0A0A;">§ 03.0 SANDBOX INFERENSI</span>
@endsection

@section('content')

<div style="max-width:880px;margin:0 auto;" x-data="liveClassifier()">

    {{-- Monograph Section Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1.25rem;margin-bottom:2rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                <span class="badge badge-black">SEKSI § 03.0</span>
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">LABORATORIUM LINGUISTIK AI</span>
            </div>
            <h1 style="font-size:2.25rem;font-weight:900;letter-spacing:-0.035em;color:#0A0A0A;margin:0;line-height:1.1;">
                LIVE INFERENCE SANDBOX
            </h1>
            <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
                Uji kecerdasan klasifikasi hierarkis IndoBERT dan normalisasi Kamusalay 15k pada kalimat tunggal secara instan.
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
                <span class="stat-block-label" style="margin:0;">SAMPEL PRESET UJI CEPAT:</span>
                <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">KLIK UNTUK MEMUAT TEKS CONTOH</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                <button type="button" @click="inputText = 'wajarlah dia awal nya kan juga anggota freemason (yahudi).'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Hoaks Pemicu Kebencian
                </button>
                <button type="button" @click="inputText = 'kerjaan dewan pengkhianat rakyat: ruu yg menguntungkan penguasa dikebut, ruu yg pro rakyat gak pernah beres.'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Delegitimasi Institusi
                </button>
                <button type="button" @click="inputText = 'kapan sih anjing2 kena azab'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Dehumanisasi
                </button>
                <button type="button" @click="inputText = 'tembak mati dnk biar seru'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Ajakan Kekerasan
                </button>
                <button type="button" @click="inputText = 'semoga semua pejabat yang dzalim dapet azab gara-gara menyengsarakan rakyat'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-danger);font-weight:900;margin-right:0.25rem;">●</span> Kutukan Agama & Personal
                </button>
                <button type="button" @click="inputText = 'langkah transparansi ini penting banget, dpr lanjutkan biar publik ikut terlibat'"
                        class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.75rem;text-transform:none;border-radius:0;">
                    <span style="color:var(--color-primary);font-weight:900;margin-right:0.25rem;">●</span> Tidak Relevan / Netral
                </button>
            </div>
        </div>

        {{-- Text Input Area --}}
        <div style="margin-bottom:1rem;">
            <label class="label" for="predict-text">INPUT TEKS VERBATIM:</label>
            <textarea id="predict-text"
                      x-model="inputText"
                      placeholder="Ketik atau tempel teks bahasa Indonesia di sini (termasuk bahasa gaul, singkatan slang, atau dialek medsos)..."
                      class="input"
                      rows="4"
                      style="resize:vertical;font-size:0.9375rem;line-height:1.6;font-family:var(--font-sans);"></textarea>
            
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.375rem;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">
                <span>PIPELINE: NORMALISASI KAMUSALAY (15.167 KATA) + REGEX FILTER</span>
                <span>PANJANG: <strong style="color:#0A0A0A;" x-text="inputText.length"></strong> KARAKTER</span>
            </div>
        </div>

        {{-- Execute Button --}}
        <button type="button"
                @click="classify()"
                :disabled="loading || inputText.trim().length < 4"
                :style="(loading || inputText.trim().length < 4) ? 'opacity:0.4;cursor:not-allowed;' : ''"
                class="btn btn-primary btn-lg"
                style="width:100%;height:46px;">
            <span x-show="!loading">↵ JALANKAN INFERENSI HIERARKIS INDOBERT</span>
            <span x-show="loading" x-cloak>EKSEKUSI TENSOR INDOBERT...</span>
        </button>

        {{-- Error Banner --}}
        <div x-show="errorMsg" x-cloak style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:3px 3px 0 #0A0A0A;padding:0.75rem 1rem;margin-top:1rem;">
            <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin:0 0 2px;">PERINGATAN INFERENSI:</p>
            <p style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;" x-text="errorMsg"></p>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             HASIL PREDIKSI (SWISS MONOGRAPH DOSSIER)
        ════════════════════════════════════════════════════════════ --}}
        <div x-show="result" x-cloak style="margin-top:2rem;border-top:2px solid #0A0A0A;padding-top:1.5rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="badge badge-black">HASIL INFERENSI TENSOR</span>
                    <span style="font-family:var(--font-mono);font-size:0.75rem;font-weight:700;color:var(--color-text-muted);">INDOBERT DUAL-STAGE</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="badge badge-safe" style="font-size:0.625rem;">STATUS: SUKSES</span>
                    <span class="badge badge-mono" style="font-size:0.625rem;" x-text="'LATENSI: ' + latency + 'ms'"></span>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.25rem;margin-bottom:1.5rem;">

                {{-- Level 1: Sentimen --}}
                <div class="card"
                     :style="result && result.label_lvl1 === 'hate_speech' ? 'border:2px solid #0A0A0A;border-top:5px solid var(--color-danger);box-shadow:3px 3px 0 #0A0A0A;background:#FFFFFF;' : 'border:2px solid #0A0A0A;border-top:5px solid var(--color-primary);box-shadow:3px 3px 0 #0A0A0A;background:#FFFFFF;'"
                     style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between;gap:1rem;">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                            <span class="stat-block-label">LEVEL 1 — STATUS SENTIMEN</span>
                            <template x-if="result && result.label_lvl1 === 'hate_speech'">
                                <span class="badge badge-hate" style="font-size:0.6875rem;">FLAG: UJARAN KEBENCIAN</span>
                            </template>
                            <template x-if="result && result.label_lvl1 !== 'hate_speech'">
                                <span class="badge badge-safe" style="font-size:0.6875rem;">VERIFIED: AMAN / NETRAL</span>
                            </template>
                        </div>

                        <h3 style="font-size:1.375rem;font-weight:900;letter-spacing:-0.03em;margin:0 0 0.5rem;color:#0A0A0A;"
                            x-text="result && result.label_lvl1 === 'hate_speech' ? 'Ujaran Kebencian (Hate)' : 'Non Ujaran Kebencian (Safe)'">
                        </h3>
                        <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.45;">
                            Klasifikasi biner tingkat pertama untuk menyaring konten bermuatan permusuhan.
                        </p>
                    </div>

                    <div style="border-top:1px solid var(--color-border-subtle);padding-top:0.75rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-family:var(--font-mono);font-size:0.75rem;margin-bottom:0.5rem;">
                            <span style="color:var(--color-text-muted);">PROBABILITAS KEYAKINAN:</span>
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
                            <span class="stat-block-label">LEVEL 2 — TAKSONOMI SPESIFIK</span>
                            <span class="badge badge-mono" style="font-size:0.6875rem;">6 SUB-KELAS</span>
                        </div>

                        <h3 style="font-size:1.375rem;font-weight:900;letter-spacing:-0.03em;margin:0 0 0.5rem;color:var(--color-primary);"
                            x-text="result ? formatLabel(result.label_lvl2) : '—'">
                        </h3>
                        <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.45;">
                            Pengelompokan multi-kelas tingkat kedua ke dalam taksonomi kejahatan siber akademis.
                        </p>
                    </div>

                    <div style="border-top:1px solid var(--color-border-subtle);padding-top:0.75rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;font-family:var(--font-mono);font-size:0.75rem;margin-bottom:0.5rem;">
                            <span style="color:var(--color-text-muted);">KEYAKINAN SUB-TIPE:</span>
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
                        <span class="stat-block-label" style="display:block;margin-bottom:0.15rem;">TRANSFORMASI PREPROCESSING · NORMALISASI 15.167 KAMUSALAY</span>
                        <span style="font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">Pembersihan mention, URL, regex filter, dan ekspansi kata singkatan/slang.</span>
                    </div>
                    <span class="badge badge-mono" style="font-size:0.625rem;">REGEX + KAMUSALAY</span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:1rem;align-items:stretch;">
                    <div style="background:var(--color-surface-2);border:1px solid #0A0A0A;padding:1rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.5rem;">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                                <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;">[01] TEKS ASLI (MENTAH)</span>
                                <span class="badge badge-mono" style="font-size:0.5625rem;" x-text="inputText.length + ' KARAKTER'"></span>
                            </div>
                            <p style="font-size:0.875rem;color:#0A0A0A;margin:0;line-height:1.6;font-family:var(--font-sans);word-break:break-word;" x-text="inputText"></p>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:0.625rem;color:var(--color-text-subtle);">INPUT VERBATIM PENGGUNA</span>
                    </div>

                    <div style="background:#FFFFFF;border:1px solid #0A0A0A;padding:1rem;border-left:4px solid var(--color-primary);display:flex;flex-direction:column;justify-content:space-between;gap:0.5rem;">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                                <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;color:var(--color-primary);text-transform:uppercase;">[02] HASIL NORMALISASI KAMUSALAY</span>
                                <span class="badge badge-safe" style="font-size:0.5625rem;">INDOBERT READY</span>
                            </div>
                            <p style="font-size:0.875rem;color:#0A0A0A;margin:0;line-height:1.6;font-family:var(--font-mono);word-break:break-word;" x-text="result ? result.clean_text : '—'"></p>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:0.625rem;color:var(--color-primary);font-weight:700;">TERNORMALISASI SECARA OTOMATIS</span>
                    </div>
                </div>
            </div>

        </div>

    </div>

    {{-- Metodologi Akademis --}}
    <div class="card-flat" style="padding:1.25rem 1.5rem;">
        <span class="stat-block-label" style="display:block;margin-bottom:0.5rem;">CATATAN METODOLOGI HIERARKIS INDOBERT</span>
        <ol style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);line-height:1.8;padding-left:1.25rem;margin:0;">
            <li><strong>Tahap Preprocessing:</strong> Pembersihan mention/URL, tokenisasi regex, dan normalisasi 15.167 entri slang Indonesia (Kamusalay).</li>
            <li><strong>Tahap Level 1:</strong> Binary Classification mendeteksi Ujaran Kebencian (Hate Speech) vs Konten Netral/Aman.</li>
            <li><strong>Tahap Level 2:</strong> Multi-class Classification mengelompokkan ke dalam 6 sub-tipe: <em>Delegitimasi Institusi, Dehumanisasi, Ajakan Kekerasan, Hoaks Pemicu Kebencian, Kutukan Agama & Personal</em>, atau <em>Tidak Relevan</em>.</li>
        </ol>
    </div>

</div>

@endsection

@push('scripts')
<script>
function liveClassifier() {
    return {
        inputText: '',
        loading:   false,
        result:    null,
        errorMsg:  '',
        latency:   0,

        async classify() {
            this.loading  = true;
            this.result   = null;
            this.errorMsg = '';
            this.latency  = 0;
            const t0 = performance.now();

            try {
                const response = await fetch('{{ route("predict.classify") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ text: this.inputText }),
                });

                const data = await response.json();
                this.latency = Math.round(performance.now() - t0);

                if (!response.ok || !data.success) {
                    this.errorMsg = data.message || 'Server FastAPI tidak dapat dihubungi di port 8080.';
                } else {
                    this.result = data.data;
                }
            } catch (e) {
                this.latency  = Math.round(performance.now() - t0);
                this.errorMsg = 'Koneksi ke backend AI gagal. Pastikan server FastAPI (uvicorn) sudah berjalan di port 8080.';
            } finally {
                this.loading = false;
            }
        },

        formatLabel(label) {
            const map = {
                'delegitimasi_institusi': 'Delegitimasi Institusi',
                'dehumanisasi':           'Dehumanisasi',
                'ajakan_kekerasan':       'Ajakan Kekerasan',
                'hoaks_pemicu_kebencian': 'Hoaks Pemicu Kebencian',
                'hoax_pemicu_kebencian':  'Hoaks Pemicu Kebencian',
                'kutukan_agama_personal': 'Kutukan Agama & Personal',
                'tidak_relevan':          'Tidak Relevan / Netral',
            };
            return map[label] || label || '—';
        }
    }
}
</script>
@endpush
