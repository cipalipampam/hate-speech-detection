@extends('layouts.app')

@section('title', 'Live Text Classifier')

@section('breadcrumb')
<div style="display:flex;align-items:center;gap:0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">Live Text Classifier</span>
</div>
@endsection

@section('content')

<div style="max-width:800px;margin:0 auto;">

    <div class="page-header">
        <h1 class="page-title">Live Text Classifier</h1>
        <p class="page-subtitle">Uji teks secara langsung menggunakan model IndoBERT tanpa perlu menjalankan scraping.</p>
    </div>

    {{-- Form Input --}}
    <div class="card" style="padding:1.5rem;margin-bottom:1.25rem;" x-data="liveClassifier()">

        <div style="margin-bottom:1.25rem;">
            <label class="label" for="predict-text">Masukkan Teks yang Ingin Dianalisis</label>
            <textarea id="predict-text"
                      x-model="inputText"
                      placeholder="Ketik atau tempel teks berbahasa Indonesia di sini..."
                      class="input"
                      rows="4"
                      style="resize:vertical;font-size:0.9375rem;line-height:1.65;"></textarea>
            <div style="display:flex;justify-content:space-between;margin-top:0.375rem;">
                <p style="font-size:0.75rem;color:var(--color-text-muted);">Mendukung teks bahasa Indonesia, termasuk slang dan bahasa informal.</p>
                <p style="font-size:0.75rem;color:var(--color-text-muted);" x-text="inputText.length + ' karakter'"></p>
            </div>
        </div>

        {{-- Contoh Cepat --}}
        <div style="margin-bottom:1.25rem;">
            <p style="font-size:0.75rem;font-weight:700;color:var(--color-text-muted);margin-bottom:0.5rem;">CONTOH CEPAT:</p>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                @foreach([
                    'Dasar pejabat tidak becus, merusak bangsa ini!',
                    'Selamat hari raya, semoga selalu dalam kebaikan.',
                    'Mereka harus diusir dari negara ini karena merusak!',
                ] as $sample)
                <button type="button"
                        @click="inputText = '{{ $sample }}'"
                        class="btn btn-ghost btn-sm"
                        style="font-size:0.75rem;text-align:left;white-space:normal;height:auto;padding:0.35rem 0.75rem;">
                    "{{ Str::limit($sample, 40) }}"
                </button>
                @endforeach
            </div>
        </div>

        <button type="button"
                @click="classify()"
                :disabled="loading || inputText.trim().length < 5"
                :style="(loading || inputText.trim().length < 5) ? 'opacity:0.6;cursor:not-allowed;' : ''"
                class="btn btn-primary btn-lg"
                style="width:100%;">
            <svg x-show="!loading" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <svg x-show="loading" x-cloak width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            <span x-text="loading ? 'Menganalisis...' : 'Analisis Sekarang'"></span>
        </button>

        {{-- Error --}}
        <div x-show="errorMsg" x-cloak class="alert alert-danger" style="margin-top:1rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <p style="font-size:0.875rem;" x-text="errorMsg"></p>
        </div>

        {{-- ─── HASIL ─── --}}
        <div x-show="result" x-cloak x-transition style="margin-top:1.5rem;border-top:1px solid var(--color-border);padding-top:1.5rem;">
            <p style="font-size:0.75rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;">HASIL KLASIFIKASI</p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">

                {{-- Level 1 --}}
                <div style="border:2px solid transparent;border-radius:1rem;padding:1.125rem;"
                     :style="result && result.label_lvl1 === 'hate_speech'
                        ? 'background:var(--color-danger-bg);border-color:rgba(239,68,68,0.25);'
                        : 'background:var(--color-teal-bg);border-color:rgba(42,157,143,0.25);'">
                    <p style="font-size:0.75rem;font-weight:700;color:var(--color-text-muted);margin-bottom:0.375rem;">LEVEL 1 — SENTIMEN</p>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.625rem;">
                        <div style="width:10px;height:10px;border-radius:50%;"
                             :style="result && result.label_lvl1 === 'hate_speech' ? 'background:var(--color-danger);' : 'background:var(--color-teal);'"></div>
                        <p style="font-size:1.125rem;font-weight:800;"
                           :style="result && result.label_lvl1 === 'hate_speech' ? 'color:var(--color-danger);' : 'color:var(--color-teal);'"
                           x-text="result && result.label_lvl1 === 'hate_speech' ? 'Ujaran Kebencian' : 'Bukan Kebencian'"></p>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.375rem;">
                        <span style="font-size:0.75rem;color:var(--color-text-muted);">Confidence</span>
                        <span style="font-size:0.875rem;font-weight:800;" x-text="result ? (result.confidence_lvl1 * 100).toFixed(1) + '%' : '—'"></span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill"
                             :style="result ? 'width:' + (result.confidence_lvl1 * 100) + '%;background:' + (result.label_lvl1 === 'hate_speech' ? 'var(--color-danger)' : 'var(--color-teal)') : 'width:0%'"></div>
                    </div>
                </div>

                {{-- Level 2 --}}
                <div style="background:var(--color-primary-bg);border:2px solid rgba(231,111,81,0.2);border-radius:1rem;padding:1.125rem;">
                    <p style="font-size:0.75rem;font-weight:700;color:var(--color-text-muted);margin-bottom:0.375rem;">LEVEL 2 — KATEGORI</p>
                    <p style="font-size:1.125rem;font-weight:800;color:var(--color-primary-dark);margin-bottom:0.625rem;"
                       x-text="result ? formatLabel(result.label_lvl2) : '—'"></p>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.375rem;">
                        <span style="font-size:0.75rem;color:var(--color-text-muted);">Confidence</span>
                        <span style="font-size:0.875rem;font-weight:800;color:var(--color-primary-dark);"
                              x-text="result && result.confidence_lvl2 ? (result.confidence_lvl2 * 100).toFixed(1) + '%' : '—'"></span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill"
                             :style="result && result.confidence_lvl2 ? 'width:' + (result.confidence_lvl2 * 100) + '%;' : 'width:0%'"></div>
                    </div>
                </div>
            </div>

            {{-- Teks Bersih --}}
            <div style="background:var(--color-surface-2);border:1px solid var(--color-border);border-radius:0.875rem;padding:1rem;">
                <p style="font-size:0.75rem;font-weight:700;color:var(--color-text-muted);margin-bottom:0.375rem;">TEKS SETELAH PREPROCESSING</p>
                <p style="font-size:0.875rem;color:var(--color-text-body);line-height:1.65;font-style:italic;" x-text="result ? result.clean_text : '—'"></p>
            </div>

        </div>
    </div>

    {{-- Petunjuk penggunaan --}}
    <div class="card-flat" style="padding:1.25rem;">
        <p style="font-size:0.8125rem;font-weight:700;color:var(--color-text-muted);margin-bottom:0.75rem;">ℹ️ PETUNJUK PENGGUNAAN</p>
        <ul style="font-size:0.8125rem;color:var(--color-text-muted);line-height:1.8;padding-left:1.25rem;">
            <li>Masukkan teks bahasa Indonesia minimal 5 karakter.</li>
            <li>Model akan membersihkan teks otomatis: normalisasi slang, hapus tanda baca, dll.</li>
            <li>Hasil Level 1 menentukan apakah teks mengandung ujaran kebencian.</li>
            <li>Hasil Level 2 mengidentifikasi sub-kategori jika terdeteksi sebagai hate speech.</li>
            <li>Confidence Score menunjukkan tingkat keyakinan model (semakin tinggi = semakin yakin).</li>
        </ul>
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

        async classify() {
            this.loading  = true;
            this.result   = null;
            this.errorMsg = '';

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

                if (!response.ok || !data.success) {
                    this.errorMsg = data.message || 'Terjadi kesalahan. Pastikan server AI sedang berjalan.';
                } else {
                    this.result = data.data;
                }
            } catch (e) {
                this.errorMsg = 'Tidak dapat terhubung ke server. Periksa koneksi Anda.';
            } finally {
                this.loading = false;
            }
        },

        formatLabel(label) {
            const map = {
                'delegitimasi_institusi': 'Delegitimasi Institusi',
                'dehumanisasi':           'Dehumanisasi',
                'ajakan_kekerasan':       'Ajakan Kekerasan',
                'hoax_pemicu_kebencian':  'Hoax Pemicu Kebencian',
                'kutukan_agama_personal': 'Kutukan Agama & Personal',
                'tidak_relevan':          'Tidak Relevan / Netral',
            };
            return map[label] || label || '—';
        }
    }
}
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
