<?php

namespace App\Http\Controllers;

use App\Http\Requests\Analysis\StoreAnalysisRequest;
use App\Models\Analysis;
use App\Services\AnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalysisController extends Controller
{
    public function __construct(
        protected AnalysisService $analysisService
    ) {}

    /**
     * Daftar semua sesi analisis (Admin: semua, Analyst: milik sendiri) dengan filter & pagination.
     */
    public function index(Request $request): View
    {
        $userId = auth()->user()->hasRole('admin') ? null : auth()->id();
        
        $analyses = $this->analysisService->getPaginatedAnalyses(
            userId:   $userId,
            perPage:  12,
            search:   $request->query('search'),
            platform: $request->query('platform'),
            status:   $request->query('status')
        );

        return view('analyses.index', compact('analyses'));
    }

    /**
     * Tampilkan form analisis baru.
     */
    public function create(): View
    {
        return view('analyses.create');
    }

    /**
     * Proses form dan mulai sesi analisis.
     */
    public function store(StoreAnalysisRequest $request): RedirectResponse
    {
        $analysis = $this->analysisService->startAnalysis(
            data: $request->validated(),
            userId: auth()->id()
        );

        if ($analysis->status === 'failed') {
            return back()
                ->withInput()
                ->with('error', 'Gagal memulai analisis: ' . ($analysis->error_message ?? 'Server AI tidak merespons.'));
        }

        return redirect()
            ->route('analyses.show', $analysis->id)
            ->with('success', 'Analisis "' . $analysis->title . '" berhasil dimulai dan sedang berjalan di background.');
    }

    /**
     * Detail dan progress satu sesi analisis.
     */
    public function show(Request $request, Analysis $analysis): View
    {
        // Sinkronisasi status jika masih running
        if ($analysis->status === 'running') {
            $analysis = $this->analysisService->syncAnalysisStatus($analysis);
        }

        $analysis->load(['statistic', 'exports', 'user']);

        // Ambil data postingan hasil analisis dengan filter & pagination
        $filters = [
            'search'     => $request->query('search'),
            'label_lvl1' => $request->query('label_lvl1'),
            'label_lvl2' => $request->query('label_lvl2'),
            'platform'   => $request->query('platform'),
        ];

        $posts = $this->analysisService->getFilteredPosts($analysis, $filters, 20);

        return view('analyses.show', compact('analysis', 'posts', 'filters'));
    }

    /**
     * Mengunduh / mengekspor dataset hasil analisis sebagai file CSV stream.
     */
    public function exportCsv(Analysis $analysis): StreamedResponse
    {
        $safeTitle = Str::slug($analysis->title, '_');
        $filename  = "hatesense_analisis_{$analysis->id}_{$safeTitle}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($analysis) {
            $handle = fopen('php://output', 'w');

            // Tulis BOM UTF-8 agar karakter Indonesia/emoji terbaca mulus di Excel
            fputs($handle, "\xEF\xBB\xBF");

            // Header Kolom CSV
            fputcsv($handle, [
                'ID',
                'Platform',
                'Author Username',
                'Tanggal Post',
                'Konten Asli (Raw Text)',
                'Konten Preprocessing (Clean Text)',
                'Sentimen Level 1',
                'Confidence Level 1',
                'Kategori Level 2',
                'Confidence Level 2',
                'Source URL',
            ]);

            // Query chunking hemat memori
            $analysis->posts()
                ->with('classification')
                ->chunk(200, function ($posts) use ($handle) {
                    foreach ($posts as $post) {
                        $c = $post->classification;
                        fputcsv($handle, [
                            $post->id,
                            $post->platform,
                            $post->author_username ?? '-',
                            $post->post_date ?? '-',
                            $c->raw_content ?? '',
                            $c->clean_content ?? '',
                            $c->label_lvl1 ?? '',
                            $c ? round($c->confidence_lvl1, 4) : 0,
                            $c ? str_replace('_', ' ', ucwords($c->label_lvl2 ?? '')) : '',
                            $c ? round($c->confidence_lvl2, 4) : 0,
                            $post->source_url ?? '',
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}

