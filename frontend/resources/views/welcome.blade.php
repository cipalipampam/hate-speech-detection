<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HateSense ID — Platform riset deteksi ujaran kebencian multi-platform berbasis Hierarchical IndoBERT. Analisis sentimen postingan X & Threads secara otomatis.">
    <title>HateSense ID — Sistem Deteksi Ujaran Kebencian</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="background-color:var(--color-canvas);" x-data="{ mobileMenu: false }">

{{-- ═══════════════════════════════════════════════════════════
     NAVBAR
════════════════════════════════════════════════════════════ --}}
<nav style="position:fixed;top:0;left:0;right:0;z-index:50;padding:0 1.5rem;height:64px;display:flex;align-items:center;background:rgba(250,246,240,0.85);backdrop-filter:blur(12px);border-bottom:1px solid var(--color-border);">
    <div style="max-width:1200px;width:100%;margin:0 auto;display:flex;align-items:center;justify-content:space-between;">

        {{-- Logo --}}
        <a href="/" style="display:flex;align-items:center;gap:0.625rem;text-decoration:none;">
            <div style="width:34px;height:34px;border-radius:0.625rem;background:linear-gradient(135deg,#E76F51,#F4A261);display:flex;align-items:center;justify-content:center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </div>
            <span style="font-weight:800;font-size:1rem;color:var(--color-navy);">HateSense <span style="color:var(--color-primary);">ID</span></span>
        </a>

        {{-- Nav Links Desktop --}}
        <div style="display:flex;align-items:center;gap:2rem;" class="hidden-mobile">
            <a href="#fitur" style="font-size:0.875rem;font-weight:600;color:var(--color-text-body);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--color-primary)'" onmouseout="this.style.color='var(--color-text-body)'">Fitur</a>
            <a href="#cara-kerja" style="font-size:0.875rem;font-weight:600;color:var(--color-text-body);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--color-primary)'" onmouseout="this.style.color='var(--color-text-body)'">Cara Kerja</a>
            <a href="#kategori" style="font-size:0.875rem;font-weight:600;color:var(--color-text-body);text-decoration:none;transition:color 0.15s;" onmouseover="this.style.color='var(--color-primary)'" onmouseout="this.style.color='var(--color-text-body)'">Kategori</a>
        </div>

        {{-- CTA Button --}}
        <div class="hidden-mobile">
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:0.375rem;">
                    <span>Dashboard</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:0.375rem;">
                    <span>Masuk ke Portal</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            @endauth
        </div>
    </div>
</nav>

{{-- ═══════════════════════════════════════════════════════════
     HERO SECTION
════════════════════════════════════════════════════════════ --}}
<section style="min-height:100vh;display:flex;align-items:center;padding-top:64px;">
    <div style="max-width:1200px;margin:0 auto;padding:5rem 1.5rem;display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center;" class="hero-grid">

        {{-- Left: Text --}}
        <div>
            {{-- Badge --}}
            <div style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--color-primary-bg);border:1.5px solid rgba(231,111,81,0.25);border-radius:999px;padding:0.35rem 0.875rem;margin-bottom:1.5rem;">
                <div style="width:7px;height:7px;border-radius:50%;background:var(--color-primary);animation:pulse-orange 2s infinite;"></div>
                <span style="font-size:0.75rem;font-weight:700;color:var(--color-primary);">RISET TUGAS AKHIR — UNIVERSITAS XYZ</span>
            </div>

            {{-- Heading --}}
            <h1 style="font-size:clamp(2rem,4.5vw,3rem);font-weight:800;color:var(--color-navy);line-height:1.15;margin-bottom:1.25rem;">
                Sistem Deteksi<br>
                <span style="color:var(--color-primary);">Ujaran Kebencian</span><br>
                Multi-Platform
            </h1>

            {{-- Sub --}}
            <p style="font-size:1.0625rem;color:var(--color-text-muted);line-height:1.7;margin-bottom:2rem;max-width:480px;">
                Mengidentifikasi hate speech dari postingan <strong style="color:var(--color-text-body);">X (Twitter)</strong> dan <strong style="color:var(--color-text-body);">Threads</strong> secara otomatis menggunakan model AI <strong style="color:var(--color-text-body);">Hierarchical IndoBERT</strong> dengan akurasi tinggi.
            </p>

            {{-- CTA Buttons --}}
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:2.5rem;">
                @auth
                    <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-lg">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Mulai Analisis
                    </a>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline btn-lg">
                        Buka Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        Mulai Analisis
                    </a>
                    <a href="#cara-kerja" class="btn btn-outline btn-lg">
                        Lihat Cara Kerja
                    </a>
                @endauth
            </div>

            {{-- Stats Strip --}}
            <div style="display:flex;gap:2rem;flex-wrap:wrap;">
                <div>
                    <p style="font-size:1.5rem;font-weight:800;color:var(--color-navy);">15.000+</p>
                    <p style="font-size:0.8125rem;color:var(--color-text-muted);font-weight:500;">Kosakata Slang (Kamusalay)</p>
                </div>
                <div style="width:1px;background:var(--color-border);"></div>
                <div>
                    <p style="font-size:1.5rem;font-weight:800;color:var(--color-navy);">6</p>
                    <p style="font-size:0.8125rem;color:var(--color-text-muted);font-weight:500;">Sub-Kategori Hate Speech</p>
                </div>
                <div style="width:1px;background:var(--color-border);"></div>
                <div>
                    <p style="font-size:1.5rem;font-weight:800;color:var(--color-navy);">2</p>
                    <p style="font-size:0.8125rem;color:var(--color-text-muted);font-weight:500;">Platform Media Sosial</p>
                </div>
            </div>
        </div>

        {{-- Right: Visual Card --}}
        <div>
            {{-- Main Card --}}
            <div class="card" style="padding:1.5rem;border-radius:1.5rem;box-shadow:0 12px 32px rgba(30,58,76,0.08);border:1px solid var(--color-border);">

                {{-- Header --}}
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;gap:0.75rem;flex-wrap:wrap;">
                    <div>
                        <p style="font-size:0.75rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.06em;margin:0 0 2px 0;">Hasil Analisis</p>
                        <p style="font-size:1.0625rem;font-weight:800;color:var(--color-navy);margin:0;">Demo Klasifikasi Teks</p>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span class="badge" style="background:#EEF3F8;color:var(--color-navy);font-weight:700;font-size:0.75rem;padding:4px 9px;border:1px solid var(--color-border);display:inline-flex;align-items:center;gap:4px;">
                            <span>🧠</span> IndoBERT
                        </span>
                        <span class="badge badge-primary" style="font-size:0.75rem;padding:4px 9px;">AI Aktif</span>
                    </div>
                </div>

                {{-- Sample Text --}}
                <div style="background:var(--color-surface-2);border:1.5px solid var(--color-border);border-radius:0.875rem;padding:1rem;margin-bottom:1.25rem;">
                    <p style="font-size:0.8125rem;color:var(--color-text-muted);font-weight:600;margin-bottom:0.375rem;">Teks Analisis:</p>
                    <p style="font-size:0.875rem;color:var(--color-text-body);font-style:italic;line-height:1.6;margin:0;">"Dasar pejabat tidak becus, merusak bangsa!"</p>
                </div>

                {{-- Result --}}
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;background:var(--color-danger-bg);border:1px solid rgba(239,68,68,0.2);border-radius:0.75rem;padding:0.75rem 1rem;">
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <div style="width:8px;height:8px;border-radius:50%;background:var(--color-danger);"></div>
                            <span style="font-size:0.875rem;font-weight:700;color:var(--color-danger);">Ujaran Kebencian</span>
                        </div>
                        <span style="font-size:0.875rem;font-weight:800;color:var(--color-danger);">94.7%</span>
                    </div>

                    <div style="display:flex;align-items:center;justify-content:space-between;background:var(--color-primary-bg);border:1px solid rgba(231,111,81,0.2);border-radius:0.75rem;padding:0.75rem 1rem;">
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <div style="width:8px;height:8px;border-radius:50%;background:var(--color-primary);"></div>
                            <span style="font-size:0.875rem;font-weight:700;color:var(--color-primary-dark);">Delegitimasi Institusi</span>
                        </div>
                        <span style="font-size:0.875rem;font-weight:800;color:var(--color-primary-dark);">88.2%</span>
                    </div>
                </div>

                {{-- Confidence Bars --}}
                <div style="margin-top:1.125rem;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:0.375rem;">
                        <span style="font-size:0.75rem;font-weight:600;color:var(--color-text-muted);">Confidence Score</span>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--color-navy);">94.7%</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width:94.7%;"></div>
                    </div>
                </div>

                {{-- Card Footer: Platform Tags & Preprocessing Badge --}}
                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--color-border);flex-wrap:wrap;">
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#E7F3FA;color:#1DA1F2;font-size:0.6875rem;font-weight:700;padding:0.25rem 0.625rem;border-radius:999px;">𝕏 Twitter</span>
                        <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#F0F0F0;color:#333;font-size:0.6875rem;font-weight:700;padding:0.25rem 0.625rem;border-radius:999px;">⊙ Threads</span>
                    </div>
                    <span style="display:inline-flex;align-items:center;gap:0.35rem;background:rgba(42,157,143,0.12);color:var(--color-teal);font-size:0.75rem;font-weight:700;padding:0.3rem 0.75rem;border-radius:999px;border:1px solid rgba(42,157,143,0.25);">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Kamusalay (15k Slang)</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     FITUR SECTION
════════════════════════════════════════════════════════════ --}}
<section id="fitur" style="padding:5rem 1.5rem;background:#FFF;">
    <div style="max-width:1200px;margin:0 auto;">

        {{-- Header --}}
        <div style="text-align:center;margin-bottom:3rem;">
            <span style="display:inline-block;background:var(--color-primary-bg);color:var(--color-primary);font-size:0.75rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:0.3rem 0.875rem;border-radius:999px;margin-bottom:1rem;">Fitur Sistem</span>
            <h2 style="font-size:clamp(1.5rem,3vw,2.25rem);font-weight:800;color:var(--color-navy);margin-bottom:0.75rem;">Teknologi Canggih,<br>Hasil yang Akurat</h2>
            <p style="font-size:1rem;color:var(--color-text-muted);max-width:560px;margin:0 auto;line-height:1.7;">Dibangun di atas fondasi model bahasa pra-latih berbahasa Indonesia yang disesuaikan khusus untuk deteksi ujaran kebencian.</p>
        </div>

        {{-- Cards Grid --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;">

            <div class="feature-card">
                <div style="width:48px;height:48px;border-radius:0.875rem;background:var(--color-primary-bg);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <h3 style="font-size:1rem;font-weight:700;color:var(--color-navy);margin-bottom:0.5rem;">Scraping Multi-Platform</h3>
                <p style="font-size:0.875rem;color:var(--color-text-muted);line-height:1.65;">Mengambil postingan dari <strong>X (Twitter)</strong> dan <strong>Threads (Meta)</strong> secara bersamaan menggunakan Playwright browser automation.</p>
            </div>

            <div class="feature-card">
                <div style="width:48px;height:48px;border-radius:0.875rem;background:var(--color-secondary-bg);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-secondary-dark)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <h3 style="font-size:1rem;font-weight:700;color:var(--color-navy);margin-bottom:0.5rem;">Normalisasi Kamusalay</h3>
                <p style="font-size:0.875rem;color:var(--color-text-muted);line-height:1.65;">Preprocessing teks menggunakan kamus slang Indonesia <strong>15.000+ entri</strong> yang dipadukan dengan Regex, case folding, dan stopword removal.</p>
            </div>

            <div class="feature-card">
                <div style="width:48px;height:48px;border-radius:0.875rem;background:#E8F7F5;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-teal)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
                <h3 style="font-size:1rem;font-weight:700;color:var(--color-navy);margin-bottom:0.5rem;">Klasifikasi 2-Level IndoBERT</h3>
                <p style="font-size:0.875rem;color:var(--color-text-muted);line-height:1.65;">Klasifikasi hierarkis: Level 1 menentukan <em>Hate/Non-Hate</em>, Level 2 mengkategorikan ke <strong>6 sub-tipe</strong> ujaran kebencian spesifik.</p>
            </div>

            <div class="feature-card">
                <div style="width:48px;height:48px;border-radius:0.875rem;background:#EEF3F8;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-navy)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </div>
                <h3 style="font-size:1rem;font-weight:700;color:var(--color-navy);margin-bottom:0.5rem;">Visualisasi Interaktif</h3>
                <p style="font-size:0.875rem;color:var(--color-text-muted);line-height:1.65;">Dashboard dengan grafik distribusi sentimen, perbandingan platform, dan tren kategori hate speech menggunakan <strong>ApexCharts</strong>.</p>
            </div>

            <div class="feature-card">
                <div style="width:48px;height:48px;border-radius:0.875rem;background:var(--color-primary-bg);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h3 style="font-size:1rem;font-weight:700;color:var(--color-navy);margin-bottom:0.5rem;">Manajemen Multi-Role</h3>
                <p style="font-size:0.875rem;color:var(--color-text-muted);line-height:1.65;">Sistem hak akses berbasis role: <strong>Admin</strong>, <strong>Analyst</strong>, dan <strong>Viewer</strong> dengan izin yang terpisah dan terstruktur.</p>
            </div>

            <div class="feature-card">
                <div style="width:48px;height:48px;border-radius:0.875rem;background:var(--color-secondary-bg);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-secondary-dark)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </div>
                <h3 style="font-size:1rem;font-weight:700;color:var(--color-navy);margin-bottom:0.5rem;">Ekspor Data CSV</h3>
                <p style="font-size:0.875rem;color:var(--color-text-muted);line-height:1.65;">Unduh seluruh hasil analisis beserta label sentimen, skor confidence, dan data postingan dalam format <strong>CSV</strong> untuk keperluan riset lanjutan.</p>
            </div>

        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     CARA KERJA SECTION
════════════════════════════════════════════════════════════ --}}
<section id="cara-kerja" style="padding:5rem 1.5rem;background:var(--color-canvas);">
    <div style="max-width:1000px;margin:0 auto;">

        <div style="text-align:center;margin-bottom:3rem;">
            <span style="display:inline-block;background:var(--color-teal-bg);color:var(--color-teal);font-size:0.75rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:0.3rem 0.875rem;border-radius:999px;margin-bottom:1rem;">Alur Sistem</span>
            <h2 style="font-size:clamp(1.5rem,3vw,2.25rem);font-weight:800;color:var(--color-navy);margin-bottom:0.75rem;">Dari Kata Kunci<br>Hingga Wawasan Mendalam</h2>
        </div>

        {{-- Steps --}}
        <div style="display:flex;flex-direction:column;gap:0;">
            @php
            $steps = [
                ['num'=>'01', 'color'=>'var(--color-primary)', 'bg'=>'var(--color-primary-bg)',
                 'title'=>'Input Kata Kunci', 'desc'=>'Peneliti memasukkan kata kunci pencarian, memilih platform target (X, Threads, atau keduanya), dan mengatur parameter scraping seperti jumlah data dan mode pencarian.',
                 'icon'=>'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
                ['num'=>'02', 'color'=>'var(--color-secondary-dark)', 'bg'=>'var(--color-secondary-bg)',
                 'title'=>'Scraping Otomatis', 'desc'=>'Server FastAPI menjalankan browser Playwright untuk mengakses media sosial dan mengambil data postingan, komentar, serta metadata pengguna secara otomatis di background.',
                 'icon'=>'M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 1 2-2V9M9 21H5a2 2 0 0 0-2-2V9m0 0h18'],
                ['num'=>'03', 'color'=>'var(--color-teal)', 'bg'=>'var(--color-teal-bg)',
                 'title'=>'Preprocessing Teks', 'desc'=>'Teks mentah dibersihkan menggunakan pipeline preprocessing: normalisasi Kamusalay, penghapusan mention/hashtag/emoji, case folding, dan tokenisasi untuk persiapan input model AI.',
                 'icon'=>'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2 14 8 20 8 M16 13 8 13 M16 17 8 17'],
                ['num'=>'04', 'color'=>'var(--color-navy)', 'bg'=>'#EEF3F8',
                 'title'=>'Klasifikasi IndoBERT', 'desc'=>'Model IndoBERT yang telah di-fine-tune melakukan klasifikasi 2 tahap: Level 1 menentukan apakah teks merupakan hate speech, Level 2 mengkategorikannya ke sub-tipe spesifik.',
                 'icon'=>'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'],
                ['num'=>'05', 'color'=>'var(--color-primary)', 'bg'=>'var(--color-primary-bg)',
                 'title'=>'Visualisasi & Ekspor', 'desc'=>'Hasil analisis disajikan dalam dashboard interaktif dengan grafik distribusi sentimen, perbandingan platform, dan tabel detail postingan. Data dapat diekspor dalam format CSV.',
                 'icon'=>'M18 20V10 M12 20V4 M6 20v-6'],
            ];
            @endphp

            @foreach($steps as $i => $step)
            <div style="display:flex;gap:1.5rem;padding:1.5rem 0;{{ !$loop->last ? 'border-bottom:1px solid var(--color-border);' : '' }}">
                {{-- Number + Line --}}
                <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                    <div style="width:52px;height:52px;border-radius:50%;background:{{ $step['bg'] }};border:2px solid {{ $step['color'] }};display:flex;align-items:center;justify-content:center;font-size:0.875rem;font-weight:800;color:{{ $step['color'] }};flex-shrink:0;">
                        {{ $step['num'] }}
                    </div>
                    @if(!$loop->last)
                    <div style="width:2px;flex:1;background:var(--color-border);margin:4px 0;min-height:32px;"></div>
                    @endif
                </div>
                {{-- Content --}}
                <div style="padding-top:0.75rem;padding-bottom:1rem;">
                    <h3 style="font-size:1.0625rem;font-weight:700;color:var(--color-navy);margin-bottom:0.5rem;">{{ $step['title'] }}</h3>
                    <p style="font-size:0.9rem;color:var(--color-text-muted);line-height:1.7;">{{ $step['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     6 KATEGORI SECTION
════════════════════════════════════════════════════════════ --}}
<section id="kategori" style="padding:5rem 1.5rem;background:#FFF;">
    <div style="max-width:1200px;margin:0 auto;">

        <div style="text-align:center;margin-bottom:3rem;">
            <span style="display:inline-block;background:var(--color-danger-bg);color:var(--color-danger);font-size:0.75rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;padding:0.3rem 0.875rem;border-radius:999px;margin-bottom:1rem;">Taksonomi Hate Speech</span>
            <h2 style="font-size:clamp(1.5rem,3vw,2.25rem);font-weight:800;color:var(--color-navy);margin-bottom:0.75rem;">6 Sub-Kategori<br>Level Klasifikasi</h2>
            <p style="font-size:1rem;color:var(--color-text-muted);max-width:520px;margin:0 auto;line-height:1.7;">Model Level 2 mengidentifikasi jenis ujaran kebencian secara spesifik untuk riset yang lebih mendalam.</p>
        </div>

        @php
        $cats = [
            ['label'=>'Delegitimasi Institusi', 'desc'=>'Ujaran yang melemahkan legitimasi lembaga negara, pemerintah, atau otoritas publik.', 'color'=>'#E76F51', 'bg'=>'#FDEAE4'],
            ['label'=>'Dehumanisasi', 'desc'=>'Ujaran yang merendahkan martabat manusia dengan menyamakannya dengan hewan atau objek.', 'color'=>'#C52B2B', 'bg'=>'#FEE2E2'],
            ['label'=>'Ajakan Kekerasan', 'desc'=>'Konten yang secara eksplisit maupun implisit mendorong tindakan kekerasan terhadap individu/kelompok.', 'color'=>'#B45309', 'bg'=>'#FEF3C7'],
            ['label'=>'Hoax Pemicu Kebencian', 'desc'=>'Penyebaran informasi palsu yang bertujuan untuk memicu kebencian atau diskriminasi.', 'color'=>'#2D5268', 'bg'=>'#EEF3F8'],
            ['label'=>'Kutukan Agama & Personal', 'desc'=>'Serangan verbal berbasis agama, suku, atau karakteristik personal seseorang.', 'color'=>'#7C3AED', 'bg'=>'#EDE9FE'],
            ['label'=>'Tidak Relevan / Netral', 'desc'=>'Teks yang tidak mengandung unsur hate speech namun tetap perlu diidentifikasi.', 'color'=>'#2A9D8F', 'bg'=>'#E6F7F5'],
        ];
        @endphp

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;">
            @foreach($cats as $cat)
            <div style="background:#FFF;border:1.5px solid var(--color-border);border-radius:1rem;padding:1.25rem;transition:all 0.2s;"
                 onmouseover="this.style.borderColor='{{ $cat['color'] }}';this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,0.08)'"
                 onmouseout="this.style.borderColor='var(--color-border)';this.style.transform='';this.style.boxShadow=''">
                <div style="display:flex;align-items:center;gap:0.625rem;margin-bottom:0.75rem;">
                    <div style="width:10px;height:10px;border-radius:50%;background:{{ $cat['color'] }};flex-shrink:0;"></div>
                    <span style="font-size:0.875rem;font-weight:700;color:{{ $cat['color'] }};">{{ $cat['label'] }}</span>
                </div>
                <p style="font-size:0.8125rem;color:var(--color-text-muted);line-height:1.65;">{{ $cat['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>



{{-- ═══════════════════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════════════════ --}}
<footer style="border-top:1px solid var(--color-border);padding:2rem 1.5rem;background:#FFF;">
    <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div style="display:flex;align-items:center;gap:0.625rem;">
            <div style="width:28px;height:28px;border-radius:0.5rem;background:linear-gradient(135deg,#E76F51,#F4A261);display:flex;align-items:center;justify-content:center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FFF" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <span style="font-weight:700;font-size:0.875rem;color:var(--color-navy);">HateSense ID</span>
        </div>
        <p style="font-size:0.8125rem;color:var(--color-text-muted);">Sistem Deteksi Ujaran Kebencian Multi-Platform · Tugas Akhir · {{ date('Y') }}</p>
        <p style="font-size:0.8125rem;color:var(--color-text-muted);">Dibangun dengan Laravel · FastAPI · IndoBERT</p>
    </div>
</footer>

<style>
@media (max-width: 768px) {
    .hero-grid { grid-template-columns: 1fr !important; }
    .hidden-mobile { display: none !important; }
}
</style>

</body>
</html>
