<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HateSense ID Lab — Platform Riset & Deteksi Ujaran Kebencian Multi-Platform berbasis Hierarchical IndoBERT.">
    <title>HateSense ID Lab — Platform Deteksi Ujaran Kebencian Multi-Platform</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body id="top" style="background-color:var(--color-canvas);color:#0A0A0A;font-family:var(--font-sans);">

{{-- ═══════════════════════════════════════════════════════════
     MONOGRAPH TOP MASTHEAD
════════════════════════════════════════════════════════════ --}}
<header class="masthead">
    <div style="display:flex;align-items:center;height:100%;">
        <a href="#top" class="brand-logo-link" style="display:flex;align-items:center;gap:0.75rem;text-decoration:none;padding-right:1.25rem;border-right:1px solid var(--color-border);height:100%;">
            <div style="background:#0A0A0A;color:#FFFFFF;font-family:var(--font-mono);font-weight:900;font-size:0.875rem;padding:0.25rem 0.5rem;line-height:1;border:1px solid #0A0A0A;">
                HS
            </div>
            <div>
                <span style="font-weight:900;font-size:0.9375rem;color:#0A0A0A;letter-spacing:-0.03em;display:block;line-height:1.1;">
                    HATESENSE <span style="color:var(--color-primary);">ID LAB</span>
                </span>
                <span style="font-family:var(--font-mono);font-size:0.625rem;color:var(--color-text-muted);letter-spacing:0.04em;">
                    LAB RISET & DETEKSI NLP
                </span>
            </div>
        </a>

        <nav class="masthead-nav hidden md:flex">
            <a href="#fitur" class="masthead-link">§ 01.0 FITUR SISTEM</a>
            <a href="#taksonomi" class="masthead-link">§ 02.0 TAKSONOMI 6 KELAS</a>
        </nav>
    </div>

    <div style="display:flex;align-items:center;gap:0.75rem;">
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">
                <span>BUKA DASHBOARD [→]</span>
            </a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
                <span>MASUK PORTAL [→]</span>
            </a>
        @endauth
    </div>
</header>

{{-- ═══════════════════════════════════════════════════════════
     HERO SECTION (SWISS MONOGRAPH)
════════════════════════════════════════════════════════════ --}}
<section style="padding:5rem 1.5rem 4rem;max-width:1440px;margin:56px auto 0;">
    <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:3rem;align-items:center;" class="hero-split">

        {{-- Left: Typographic Statement --}}
        <div>
            <div style="display:inline-flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem;">
                <span class="badge badge-black">RESEARCH PLATFORM</span>
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">BENCHMARK KORPUS BAHASA INDONESIA</span>
            </div>

            <h1 style="font-size:clamp(2.5rem,5vw,3.75rem);font-weight:900;letter-spacing:-0.04em;line-height:1.05;color:#0A0A0A;margin:0 0 1.25rem;">
                SISTEM DETEKSI<br>
                <span style="background:var(--color-danger);padding:0 0.25rem;border:2px solid #0A0A0A;">UJARAN KEBENCIAN</span><br>
                MULTI-PLATFORM
            </h1>

            <p style="font-size:1.0625rem;line-height:1.7;color:#262626;max-width:540px;margin:0 0 2rem;">
                Identifikasi provokasi dan ujaran kebencian dari korpus media sosial <strong style="color:#0A0A0A;">Twitter (𝕏)</strong> dan <strong style="color:#0A0A0A;">Threads</strong> menggunakan model pra-latih <strong style="color:#0A0A0A;">Hierarchical IndoBERT</strong> dan kamus normalisasi Kamusalay 15.000 entri.
            </p>

            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:2.5rem;">
                @auth
                    <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-lg">
                        <span>+ MULAI INVESTIGASI BARU</span>
                    </a>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline btn-lg">
                        <span>MENUJU OVERVIEW</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
                        <span>MASUK KE PORTAL LAB</span>
                    </a>
                    <a href="#taksonomi" class="btn btn-outline btn-lg">
                        <span>TAKSONOMI 6 KELAS [↓]</span>
                    </a>
                @endauth
            </div>

            {{-- Metric Strip --}}
            <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:1rem;border-top:2px solid #0A0A0A;padding-top:1.25rem;">
                <div>
                    <span class="stat-block-val" style="font-size:1.75rem;">15.167</span>
                    <span class="stat-block-label" style="display:block;margin-top:2px;">KOSAKATA SLANG</span>
                </div>
                <div>
                    <span class="stat-block-val" style="font-size:1.75rem;">6 KELAS</span>
                    <span class="stat-block-label" style="display:block;margin-top:2px;">TAKSONOMI L2</span>
                </div>
                <div>
                    <span class="stat-block-val" style="font-size:1.75rem;">2 SUMBER</span>
                    <span class="stat-block-label" style="display:block;margin-top:2px;">𝕏 & ⊙ CRAWLER</span>
                </div>
            </div>
        </div>

        {{-- Right: Live Incident Inspection Showcase --}}
        <div>
            <div class="card" style="padding:1.5rem;background:#FFFFFF;border:2px solid #0A0A0A;box-shadow:6px 6px 0 #0A0A0A;">
                <div style="border-bottom:2px solid #0A0A0A;padding-bottom:0.75rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <span class="badge badge-black" style="font-size:0.625rem;">SAMPLE DOSSIER</span>
                        <h2 style="font-size:1.0625rem;font-weight:900;color:#0A0A0A;margin:2px 0 0;">Demo Klasifikasi Verbatim</h2>
                    </div>
                    <span class="badge badge-safe">INDOBERT ACTIVE</span>
                </div>

                {{-- Sample Text with XAI Highlight --}}
                <div class="card-flat" style="padding:1rem;margin-bottom:1.25rem;">
                    <span class="stat-block-label" style="margin-bottom:0.35rem;display:block;">TEKS KORPUS ANALISIS:</span>
                    <div style="font-size:0.9375rem;line-height:1.7;color:#0A0A0A;">
                        "Dasar <span class="xai-toxic">pejabat penipu</span> tidak becus, sengaja <span class="xai-toxic">merusak tatanan bangsa</span> demi kepentingan antek!"
                    </div>
                </div>

                {{-- Breakdown Blocks --}}
                <div style="display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1.25rem;">
                    <div style="background:var(--color-danger);border:1px solid #0A0A0A;padding:0.75rem;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;color:#0A0A0A;display:block;">LEVEL 1: SENTIMEN</span>
                            <span style="font-size:1rem;font-weight:900;color:#0A0A0A;">Ujaran Kebencian (Hate Speech)</span>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:1.125rem;font-weight:900;color:#0A0A0A;">96.4%</span>
                    </div>

                    <div style="background:var(--color-primary-bg);border:1px solid var(--color-primary);padding:0.75rem;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;color:var(--color-primary);display:block;">LEVEL 2: TAKSONOMI</span>
                            <span style="font-size:1rem;font-weight:900;color:var(--color-primary);">Delegitimasi Institusi</span>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:1.125rem;font-weight:900;color:var(--color-primary);">89.1%</span>
                    </div>
                </div>

                {{-- Footer Telemetry --}}
                <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid #0A0A0A;padding-top:0.75rem;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">
                    <span>LATENSI: ~42ms</span>
                    <span>NORMALISASI: 2 KATA SLANG TERGANTI</span>
                </div>
            </div>
        </div>

    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     FITUR SISTEM SECTION
════════════════════════════════════════════════════════════ --}}
<section id="fitur" style="scroll-margin-top:56px;padding:4rem 1.5rem;border-top:2px solid #0A0A0A;background:#FFFFFF;">
    <div style="max-width:1440px;margin:0 auto;">
        <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:2.5rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
                <span class="badge badge-black">SEKSI § 01.0</span>
                <h2 style="font-size:2rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0.25rem 0 0;">
                    MODUL DETEKSI & FITUR SISTEM
                </h2>
            </div>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">6 KOMPONEN INTEGRASI SISTEM</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.5rem;">
            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 01</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Crawling Multi-Platform</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Pengambilan postingan dari X (Twitter) dan Threads (Meta) secara paralel menggunakan Playwright browser automation berotentikasi sesi persisten.
                </p>
                <div style="display:flex;gap:0.375rem;">
                    <span class="badge badge-black">𝕏 TWITTER</span>
                    <span class="badge badge-mono">⊙ THREADS</span>
                </div>
            </div>

            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 02</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Normalisasi Kamusalay</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Pembersihan teks mentah dengan kamus slang 15.000+ entri, pembersihan regex untuk mention/hashtag/URL, dan case folding terstandar.
                </p>
                <div style="display:flex;gap:0.375rem;">
                    <span class="badge badge-mono">15.167 SLANG</span>
                    <span class="badge badge-safe">REGEX CLEANER</span>
                </div>
            </div>

            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 03</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Inferensi Hierarkis IndoBERT</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Klasifikasi 2 tingkat: Level 1 menyaring sentimen biner (Hate/Non-Hate), Level 2 mengidentifikasi 6 taksonomi kebencian spesifik.
                </p>
                <div style="display:flex;gap:0.375rem;">
                    <span class="badge badge-black">DUAL-LEVEL</span>
                    <span class="badge badge-safe">PYTORCH</span>
                </div>
            </div>

            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 04</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Visualisasi Komparatif</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Dashboard analitik dengan matriks disparitas platform, perbandingan volume, dan grafik distribusi sentimen ApexCharts.
                </p>
                <div style="display:flex;gap:0.375rem;">
                    <span class="badge badge-mono">APEXCHARTS</span>
                    <span class="badge badge-safe">DISPARITAS 𝕏 & ⊙</span>
                </div>
            </div>

            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 05</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Sandbox Pengujian Real-Time</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Uji coba prediksi teks tunggal secara instan tanpa proses crawling, lengkap dengan probabilitas sentimen L1 dan taksonomi L2 langsung.
                </p>
                <div style="display:flex;gap:0.375rem;">
                    <span class="badge badge-black">INSTANT INFERENCE</span>
                    <span class="badge badge-safe">INTERAKTIF</span>
                </div>
            </div>

            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 06</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Ekspor Korpus Riset (CSV)</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Unduh seluruh data hasil analisis beserta teks pra-pembersihan, teks normal, skor keyakinan, dan metadata postingan.
                </p>
                <div style="display:flex;gap:0.375rem;">
                    <span class="badge badge-mono">EXPORT CSV</span>
                    <span class="badge badge-safe">AUDIT METADATA</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     TAKSONOMI 6 SUB-KATEGORI SECTION
════════════════════════════════════════════════════════════ --}}
<section id="taksonomi" style="scroll-margin-top:56px;padding:4rem 1.5rem;border-top:2px solid #0A0A0A;background:var(--color-canvas);">
    <div style="max-width:1440px;margin:0 auto;">
        <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:2.5rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
                <span class="badge badge-black">SEKSI § 02.0</span>
                <h2 style="font-size:2rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0.25rem 0 0;">
                    TAKSONOMI 6 SUB-KATEGORI LEVEL 2
                </h2>
            </div>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">KLASIFIKASI SPESIFIK INDOBERT</span>
        </div>

        @php
        $cats = [
            ['num'=>'01', 'name'=>'Delegitimasi Institusi', 'desc'=>'Ujaran yang melemahkan legitimasi lembaga negara, penegak hukum, atau otoritas publik sah.', 'is_hate'=>true],
            ['num'=>'02', 'name'=>'Dehumanisasi', 'desc'=>'Pelecehan martabat dengan menyamakan manusia dengan hewan, penyakit, atau objek nista.', 'is_hate'=>true],
            ['num'=>'03', 'name'=>'Ajakan Kekerasan', 'desc'=>'Seruan eksplisit maupun implisit untuk melakukan agresi fisik terhadap individu atau kelompok.', 'is_hate'=>true],
            ['num'=>'04', 'name'=>'Hoaks Pemicu Kebencian', 'desc'=>'Fabrikasi informasi palsu yang sengaja disebarkan guna memicu permusuhan massa atau SARA.', 'is_hate'=>true],
            ['num'=>'05', 'name'=>'Kutukan Agama & Personal', 'desc'=>'Serangan verbal, cercaan terhadap simbol keagamaan, atau stigmatisasi ras/etnis.', 'is_hate'=>true],
            ['num'=>'06', 'name'=>'Tidak Relevan / Netral', 'desc'=>'Konten opini wajar atau diskusi publik yang tidak memenuhi kriteria ujaran kebencian.', 'is_hate'=>false],
        ];
        @endphp

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.25rem;">
            @foreach($cats as $c)
            <div class="card" style="padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;{{ !$c['is_hate'] ? 'background:var(--color-primary-bg);border-color:var(--color-primary);' : '' }}">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                        <span class="stat-block-label">KELAS § 02.{{ $c['num'] }}</span>
                        @if($c['is_hate'])
                            <span class="badge badge-hate" style="font-size:0.625rem;">TOKSIK L2</span>
                        @else
                            <span class="badge badge-safe" style="font-size:0.625rem;">NON-TOKSIK</span>
                        @endif
                    </div>
                    <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0 0 0.35rem;">{{ $c['name'] }}</h3>
                    <p style="font-size:0.8125rem;line-height:1.6;color:var(--color-text-muted);margin:0;">{{ $c['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     FOOTER (SWISS EDITORIAL)
════════════════════════════════════════════════════════════ --}}
<footer style="border-top:2px solid #0A0A0A;padding:2rem 1.5rem;background:#FFFFFF;">
    <div style="max-width:1440px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;font-family:var(--font-mono);font-size:0.75rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <div style="background:#0A0A0A;color:#FFFFFF;padding:2px 6px;font-weight:900;">HS</div>
            <span style="font-weight:800;color:#0A0A0A;">HATESENSE ID LAB · NLP RESEARCH PLATFORM</span>
        </div>
        <span style="color:var(--color-text-muted);">
            LARAVEL 11 · FASTAPI · HIERARCHICAL INDOBERT · PLAYWRIGHT · {{ date('Y') }}
        </span>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Smooth scroll for all internal anchor links (including logo #top)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (!targetId || targetId === '#') return;

            e.preventDefault();

            if (targetId === '#top') {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
                history.pushState(null, '', window.location.pathname);
                return;
            }

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
                history.pushState(null, '', targetId);
            }
        });
    });
});
</script>

</body>
</html>
