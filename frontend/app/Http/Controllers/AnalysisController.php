<?php

namespace App\Http\Controllers;

use App\Http\Requests\Analysis\FilterPostRequest;
use App\Http\Requests\Analysis\FilterAnalysisRequest;
use App\Http\Requests\Analysis\StoreAnalysisRequest;
use App\Models\Analysis;
use App\Services\AnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalysisController extends Controller
{
    public function __construct(
        protected AnalysisService $analysisService
    ) {}

    /**
     * Daftar semua sesi analisis (Admin: semua, Analyst: milik sendiri) dengan filter & pagination.
     */
    public function index(FilterAnalysisRequest $request): View|JsonResponse
    {
        $userId = auth()->user()->hasRole('admin') ? null : auth()->id();
        
        // 1. Sinkronisasi status analisis yang masih running / queued ke FastAPI.
        //    Dicek SEKALI saja dan di-scope ke user ini (admin: semua) agar indikator
        //    polling tidak menyala hanya karena ada job milik user lain.
        $hasRunning = Analysis::whereIn('status', ['running', 'queued'])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->exists();

        if ($hasRunning) {
            $this->analysisService->syncRunningAnalyses($userId);
        }

        $analyses = $this->analysisService->getPaginatedAnalyses(
            userId:   $userId,
            perPage:  (int) ($request->validated('per_page') ?? 12),
            search:   $request->validated('search'),
            platform: $request->validated('platform'),
            status:   $request->validated('status')
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'     => true,
                'has_running' => $hasRunning,
                'analyses'    => $analyses,
            ]);
        }

        return view('analyses.index', compact('analyses', 'hasRunning'));
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
    public function show(FilterPostRequest $request, Analysis $analysis): View|JsonResponse
    {
        // Otorisasi kepemilikan: admin bebas, pengguna lain hanya analisis miliknya sendiri.
        Gate::authorize('view', $analysis);

        // Sinkronisasi status jika masih running atau queued
        if ($analysis->status === 'running' || $analysis->status === 'queued') {
            $analysis = $this->analysisService->syncAnalysisStatus($analysis);
        }

        $analysis->load(['statistic', 'exports', 'user']);

        // Ambil data postingan hasil analisis dengan filter & pagination
        $filters = [
            'search'     => $request->validated('search'),
            'label_lvl1' => $request->validated('label_lvl1'),
            'label_lvl2' => $request->validated('label_lvl2'),
            'platform'   => $request->validated('platform'),
        ];
        $perPage = (int) ($request->validated('per_page') ?? 20);

        $posts = $this->analysisService->getFilteredPosts($analysis, $filters, $perPage);

        if ($request->ajax() || $request->wantsJson()) {
            $pipelineMsg = $analysis->pipeline_message;
            if ($pipelineMsg && (stripos($pipelineMsg, 'polling') !== false || stripos($pipelineMsg, 'asinkron') !== false)) {
                $pipelineMsg = 'Pipeline analisis sedang diproses oleh sistem...';
            }

            return response()->json([
                'success'   => true,
                'analysis'  => [
                    'id'                     => $analysis->id,
                    'title'                  => $analysis->title,
                    'platform'               => $analysis->platform,
                    'status'                 => $analysis->status,
                    'search_mode'            => $analysis->search_mode,
                    'max_links'              => $analysis->max_links,
                    'execution_time_seconds' => $analysis->execution_time_seconds,
                    'error_message'          => $analysis->error_message,
                    'created_at'             => $analysis->created_at ? $analysis->created_at->format('d M Y, H:i') : null,
                    'user_name'              => $analysis->user->name ?? '—',
                    'pipeline_message'       => $pipelineMsg ?? ($analysis->status === 'completed' ? 'Seluruh sekuensial pipeline AI telah selesai dieksekusi.' : 'Menghubungkan ke antrean background worker...'),
                    'pipeline_step'          => $analysis->pipeline_step ?? ($analysis->status === 'completed' ? 4 : 1),
                ],
                'statistic' => $analysis->statistic,
                'posts'     => $posts,
            ]);
        }

        return view('analyses.show', compact('analysis', 'posts', 'filters'));
    }

    /**
     * Mengunduh / mengekspor dataset hasil analisis sebagai file CSV stream.
     */
    public function exportCsv(Analysis $analysis): StreamedResponse
    {
        // Permission `export-reports` sudah dijaga middleware rute; di sini ditegaskan
        // kepemilikan analisis agar analyst tidak bisa mengunduh data peneliti lain.
        Gate::authorize('export', $analysis);

        return $this->analysisService->streamExportCsv($analysis);
    }
}

