<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HateSense ID Lab — Multi-platform hate speech detection powered by Hierarchical IndoBERT.">
    <title>HateSense ID Lab — Multi-Platform Hate Speech Detection</title>

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
                    NLP RESEARCH & DETECTION LAB
                </span>
            </div>
        </a>

        <nav class="masthead-nav hidden md:flex">
            <a href="#fitur" class="masthead-link">§ 01.0 SYSTEM FEATURES</a>
            <a href="#taksonomi" class="masthead-link">§ 02.0 SIX-CLASS TAXONOMY</a>
        </nav>
    </div>

    <div style="display:flex;align-items:center;gap:0.75rem;">
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">
                <span>OPEN DASHBOARD [→]</span>
            </a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
                <span>ENTER PORTAL [→]</span>
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
                <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">INDONESIAN LANGUAGE BENCHMARK CORPUS</span>
            </div>

            <h1 style="font-size:clamp(2.5rem,5vw,3.75rem);font-weight:900;letter-spacing:-0.04em;line-height:1.05;color:#0A0A0A;margin:0 0 1.25rem;">
                HATE SPEECH<br>
                <span style="background:var(--color-danger);padding:0 0.25rem;border:2px solid #0A0A0A;">DETECTION SYSTEM</span><br>
                MULTI-PLATFORM
            </h1>

            <p style="font-size:1.0625rem;line-height:1.7;color:#262626;max-width:540px;margin:0 0 2rem;">
                Identify provocation and hate speech in social media corpora from <strong style="color:#0A0A0A;">Twitter (𝕏)</strong> and <strong style="color:#0A0A0A;">Threads</strong> using <strong style="color:#0A0A0A;">Hierarchical IndoBERT</strong> and the 15,000-entry Kamusalay normalization lexicon.
            </p>

            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:2.5rem;">
                @auth
                    <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-lg">
                        <span>+ START NEW ANALYSIS</span>
                    </a>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline btn-lg">
                        <span>OPEN DASHBOARD</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
                        <span>ENTER LAB PORTAL</span>
                    </a>
                    <a href="#taksonomi" class="btn btn-outline btn-lg">
                        <span>SIX-CLASS TAXONOMY [↓]</span>
                    </a>
                @endauth
            </div>

            {{-- Metric Strip --}}
            <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:1rem;border-top:2px solid #0A0A0A;padding-top:1.25rem;">
                <div>
                    <span class="stat-block-val" style="font-size:1.75rem;">15.167</span>
                    <span class="stat-block-label" style="display:block;margin-top:2px;">SLANG TERMS</span>
                </div>
                <div>
                    <span class="stat-block-val" style="font-size:1.75rem;">6 CLASSES</span>
                    <span class="stat-block-label" style="display:block;margin-top:2px;">LEVEL 2 TAXONOMY</span>
                </div>
                <div>
                    <span class="stat-block-val" style="font-size:1.75rem;">2 SOURCES</span>
                    <span class="stat-block-label" style="display:block;margin-top:2px;">𝕏 & ⊙ CRAWLERS</span>
                </div>
            </div>
        </div>

        {{-- Right: Live Incident Inspection Showcase --}}
        <div>
            <div class="card" style="padding:1.5rem;background:#FFFFFF;border:2px solid #0A0A0A;box-shadow:6px 6px 0 #0A0A0A;">
                <div style="border-bottom:2px solid #0A0A0A;padding-bottom:0.75rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <span class="badge badge-black" style="font-size:0.625rem;">SAMPLE DOSSIER</span>
                        <h2 style="font-size:1.0625rem;font-weight:900;color:#0A0A0A;margin:2px 0 0;">Verbatim Classification Demo</h2>
                    </div>
                    <span class="badge badge-safe">INDOBERT ACTIVE</span>
                </div>

                {{-- Sample Text with XAI Highlight --}}
                <div class="card-flat" style="padding:1rem;margin-bottom:1.25rem;">
                    <span class="stat-block-label" style="margin-bottom:0.35rem;display:block;">ANALYSIS CORPUS TEXT:</span>
                    <div style="font-size:0.9375rem;line-height:1.7;color:#0A0A0A;">
                        "Dasar <span class="xai-toxic">pejabat penipu</span> tidak becus, sengaja <span class="xai-toxic">merusak tatanan bangsa</span> demi kepentingan antek!"
                    </div>
                </div>

                {{-- Breakdown Blocks --}}
                <div style="display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1.25rem;">
                    <div style="background:var(--color-danger);border:1px solid #0A0A0A;padding:0.75rem;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;color:#0A0A0A;display:block;">LEVEL 1: SENTIMEN</span>
                            <span style="font-size:1rem;font-weight:900;color:#0A0A0A;">Hate Speech</span>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:1.125rem;font-weight:900;color:#0A0A0A;">96.4%</span>
                    </div>

                    <div style="background:var(--color-primary-bg);border:1px solid var(--color-primary);padding:0.75rem;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <span style="font-family:var(--font-mono);font-size:0.6875rem;font-weight:800;color:var(--color-primary);display:block;">LEVEL 2: TAXONOMY</span>
                            <span style="font-size:1rem;font-weight:900;color:var(--color-primary);">Institutional Delegitimization</span>
                        </div>
                        <span style="font-family:var(--font-mono);font-size:1.125rem;font-weight:900;color:var(--color-primary);">89.1%</span>
                    </div>
                </div>

                {{-- Footer Telemetry --}}
                <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid #0A0A0A;padding-top:0.75rem;font-family:var(--font-mono);font-size:0.6875rem;color:var(--color-text-muted);">
                    <span>LATENCY: ~42ms</span>
                    <span>NORMALIZATION: 2 SLANG TERMS REPLACED</span>
                </div>
            </div>
        </div>

    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
    SYSTEM FEATURES SECTION
════════════════════════════════════════════════════════════ --}}
<section id="fitur" style="scroll-margin-top:56px;padding:4rem 1.5rem;border-top:2px solid #0A0A0A;background:#FFFFFF;">
    <div style="max-width:1440px;margin:0 auto;">
        <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:2.5rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
                <span class="badge badge-black">SEKSI § 01.0</span>
                <h2 style="font-size:2rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0.25rem 0 0;">
                    DETECTION MODULES & SYSTEM FEATURES
                </h2>
            </div>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">6 INTEGRATED COMPONENTS</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(320px, 100%), 1fr));gap:1.5rem;">
            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 01</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Crawling Multi-Platform</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Collect posts from X (Twitter) and Threads (Meta) in parallel using authenticated persistent Playwright browser sessions.
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
                    Clean raw text with a 15,000+ entry slang lexicon, regex filters for mentions, hashtags, and URLs, plus standard case folding.
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
                    Two-stage classification: Level 1 screens binary sentiment (Hate/Non-Hate), while Level 2 identifies six specific hate speech classes.
                </p>
                <div style="display:flex;gap:0.375rem;">
                    <span class="badge badge-black">DUAL-LEVEL</span>
                    <span class="badge badge-safe">PYTORCH</span>
                </div>
            </div>

            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 04</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Comparative Visualization</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Analytics dashboards with platform disparity matrices, volume comparisons, and ApexCharts sentiment distributions.
                </p>
                <div style="display:flex;gap:0.375rem;">
                    <span class="badge badge-mono">APEXCHARTS</span>
                    <span class="badge badge-safe">DISPARITAS 𝕏 & ⊙</span>
                </div>
            </div>

            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 05</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Real-Time Classifier</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Run instant single-text predictions without crawling, including Level 1 sentiment and Level 2 taxonomy probabilities.
                </p>
                <div style="display:flex;gap:0.375rem;">
                    <span class="badge badge-black">INSTANT INFERENCE</span>
                    <span class="badge badge-safe">INTERACTIVE</span>
                </div>
            </div>

            <div class="card" style="padding:1.5rem;">
                <span class="stat-block-label">MODUL 06</span>
                <h3 style="font-size:1.125rem;font-weight:900;color:#0A0A0A;margin:0.25rem 0 0.5rem;">Research Corpus Export (CSV)</h3>
                <p style="font-size:0.875rem;line-height:1.6;color:var(--color-text-muted);margin:0 0 1rem;">
                    Download analysis records with pre-cleaning text, normalized text, confidence scores, and post metadata.
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
    SIX-CLASS TAXONOMY SECTION
════════════════════════════════════════════════════════════ --}}
<section id="taksonomi" style="scroll-margin-top:56px;padding:4rem 1.5rem;border-top:2px solid #0A0A0A;background:var(--color-canvas);">
    <div style="max-width:1440px;margin:0 auto;">
        <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:2.5rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
                <span class="badge badge-black">SEKSI § 02.0</span>
                <h2 style="font-size:2rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0.25rem 0 0;">
                    SIX-CLASS LEVEL 2 TAXONOMY
                </h2>
            </div>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">INDOBERT-SPECIFIC CLASSIFICATION</span>
        </div>

        @php
        $cats = [
            ['num'=>'01', 'name'=>'Institutional Delegitimization', 'desc'=>'Language that undermines legitimate state institutions, law enforcement, or public authorities.', 'is_hate'=>true],
            ['num'=>'02', 'name'=>'Dehumanization', 'desc'=>'Attacks on dignity that compare people to animals, diseases, or degrading objects.', 'is_hate'=>true],
            ['num'=>'03', 'name'=>'Incitement to Violence', 'desc'=>'Explicit or implicit calls for physical aggression against individuals or groups.', 'is_hate'=>true],
            ['num'=>'04', 'name'=>'Hate-Baiting Hoax', 'desc'=>'Fabricated information deliberately spread to provoke mass hostility or identity-based hatred.', 'is_hate'=>true],
            ['num'=>'05', 'name'=>'Religious & Personal Abuse', 'desc'=>'Verbal attacks, contempt for religious symbols, or racial and ethnic stigmatization.', 'is_hate'=>true],
            ['num'=>'06', 'name'=>'Non-Hate / Neutral', 'desc'=>'Ordinary opinions or public discussion that do not meet hate speech criteria.', 'is_hate'=>false],
        ];
        @endphp

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(320px, 100%), 1fr));gap:1.25rem;">
            @foreach($cats as $c)
            <div class="card" style="padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;{{ !$c['is_hate'] ? 'background:var(--color-primary-bg);border-color:var(--color-primary);' : '' }}">
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                        <span class="stat-block-label">CLASS § 02.{{ $c['num'] }}</span>
                        @if($c['is_hate'])
                            <span class="badge badge-hate" style="font-size:0.625rem;">HATE L2</span>
                        @else
                            <span class="badge badge-safe" style="font-size:0.625rem;">NON-HATE</span>
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
