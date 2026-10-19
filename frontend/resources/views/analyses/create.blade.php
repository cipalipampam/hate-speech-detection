@extends('layouts.app')

@section('title', '§ 02.1 New Batch Analysis')

@section('breadcrumb')
<a href="{{ route('analyses.index') }}" style="color:var(--color-text-muted);text-decoration:none;">§ 02.0 BATCH ANALYSES</a>
<span>/</span>
<span style="color:#0A0A0A;">+ NEW ANALYSIS</span>
@endsection

@section('content')

<div style="max-width:820px;margin:0 auto;">

    {{-- Monograph Section Header --}}
    <div style="border-bottom:2px solid #0A0A0A;padding-bottom:1rem;margin-bottom:1.75rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
            <span class="badge badge-black">PIPELINE § 02.1</span>
            <span style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-text-muted);">ANALYSIS PIPELINE CONFIGURATION</span>
        </div>
        <h1 style="font-size:2rem;font-weight:900;letter-spacing:-0.03em;color:#0A0A0A;margin:0;line-height:1.1;">
            NEW BATCH ANALYSIS
        </h1>
        <p style="font-family:var(--font-mono);font-size:0.8125rem;color:var(--color-text-muted);margin:0.35rem 0 0;">
            Configure social media collection parameters for X and Threads, then run hierarchical IndoBERT inference.
        </p>
    </div>

    @if($errors->any())
    <div style="background:var(--color-danger);border:2px solid #0A0A0A;box-shadow:4px 4px 0 #0A0A0A;padding:0.875rem 1rem;margin-bottom:1.5rem;">
        <p style="font-family:var(--font-mono);font-size:0.75rem;font-weight:800;color:#0A0A0A;margin:0 0 0.25rem;">VALIDATION FAILED:</p>
        <ul style="font-size:0.8125rem;font-weight:600;color:#0A0A0A;margin:0;padding-left:1.25rem;">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- JSON Island: data server awal untuk halaman buat analisis baru --}}
    <script id="analysis-create-data" type="application/json">
        {!! json_encode([
            'keywords' => old('keywords', []),
            'platform' => old('platform', 'both'),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>

    <form method="POST" action="{{ route('analyses.store') }}" x-data="analysisForm()" id="form-analysis">
        @csrf

        {{-- Card 1: Research Identity & Keywords --}}
        @include('analyses.partials.create-identity-card')

        {{-- Card 2: Platform Target & Collection Depth --}}
        @include('analyses.partials.create-platform-card')

        {{-- Card 3: Execution Engine & Pipeline Options --}}
        @include('analyses.partials.create-pipeline-card')

        {{-- Actions --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding-top:1rem;border-top:1px solid var(--color-border);">
            <a href="{{ route('analyses.index') }}" class="btn btn-outline">← CANCEL</a>
            <button type="submit" class="btn btn-primary btn-lg"
                    :disabled="keywords.length === 0"
                    :style="keywords.length === 0 ? 'opacity:0.4;cursor:not-allowed;' : ''">
                <span>↵ RUN ANALYSIS PIPELINE</span>
            </button>
        </div>

    </form>
</div>

@endsection
