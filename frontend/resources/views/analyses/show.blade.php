@extends('layouts.app')

@section('title', '§ 02.2 Dossier #' . str_pad($analysis->id, 4, '0', STR_PAD_LEFT) . ': ' . ($analysis->title ?? 'Detail'))

@section('breadcrumb')
<a href="{{ route('analyses.index') }}" style="color:var(--color-text-muted);text-decoration:none;">§ 02.0 BATCH ANALYSES</a>
<span>/</span>
<span style="color:#0A0A0A;">DOSSIER #{{ str_pad($analysis->id, 4, '0', STR_PAD_LEFT) }}</span>
@endsection

@section('content')

{{-- JSON Island: data server awal untuk halaman detail analisis --}}
<script id="analysis-server-data" type="application/json">
    {!! json_encode([
        'analysisId'      => $analysis->id,
        'status'          => $analysis->status,
        'stats'           => $analysis->statistic,
        'keywords'        => $analysis->keywords ?? [],
        'endpoint'        => route('analyses.show', $analysis->id),
        'exportUrl'       => route('analyses.export', $analysis->id),
        'execTime'        => $analysis->execution_time_seconds,
        'pipelineStep'    => $analysis->status === 'completed' ? 4 : ($analysis->pipeline_step ?? 1),
        'pipelineMessage' => $analysis->status === 'failed'
            ? ($analysis->error_message ?? 'The AI pipeline failed. Check the AI server logs.')
            : (($analysis->pipeline_message && stripos($analysis->pipeline_message, 'polling') === false && stripos($analysis->pipeline_message, 'asinkron') === false)
                ? $analysis->pipeline_message
                : ($analysis->status === 'completed' ? 'The complete AI pipeline has finished.' : 'Connecting to the processing worker queue...')),
        'posts'           => $posts->items(),
        'selectedPost'    => $posts->items()[0] ?? null,
        'pagination'      => [
            'current_page'  => $posts->currentPage(),
            'last_page'     => $posts->lastPage(),
            'total'         => $posts->total(),
            'from'          => $posts->firstItem() ?? 0,
            'to'            => $posts->lastItem() ?? 0,
            'prev_page_url' => $posts->previousPageUrl(),
            'next_page_url' => $posts->nextPageUrl(),
        ],
        'filters'         => [
            'search'     => request('search', ''),
            'label_lvl1' => request('label_lvl1', 'all'),
            'label_lvl2' => request('label_lvl2', 'all'),
            'platform'   => request('platform', 'all'),
        ],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
</script>

<div x-data="analysisDetail()" x-init="init()"
     @keydown.window.j="selectNext()"
     @keydown.window.k="selectPrev()">

    {{-- Dossier Header Bar --}}
    @include('analyses.partials.show-header')

    {{-- State 1: Pipeline Stepper & Progress (Running / Queued / Completed overview) --}}
    @include('analyses.partials.show-pipeline-stepper')

    {{-- State 2: Completed Research Desk Workspace --}}
    <div x-show="showDetail && status === 'completed'" x-cloak style="display:flex;flex-direction:column;gap:1.5rem;">
        {{-- Metric Summary Blocks --}}
        @include('analyses.partials.show-metrics-summary')

        {{-- Dual Sentiment & Level 2 Charts --}}
        @include('analyses.partials.show-charts-row')

        {{-- Split Pane: Post Stream (Left) & Sticky Incident Inspector (Right) --}}
        <div class="split-pane-grid" style="display:grid;grid-template-columns:minmax(0, 1.8fr) minmax(0, 1.2fr);gap:1.25rem;align-items:flex-start;">
            @include('analyses.partials.show-post-stream')
            @include('analyses.partials.show-incident-inspector')
        </div>
    </div>

</div>

@endsection
