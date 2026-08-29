@extends('layouts.app')

@section('title', $analysis->title ?? 'Detail Analisis')

@section('breadcrumb')
<div style="display:flex;align-items:center;gap:0.5rem;">
    <a href="{{ route('analyses.index') }}" style="font-size:0.875rem;color:var(--color-text-muted);font-weight:500;text-decoration:none;"
       onmouseover="this.style.color='var(--color-primary)'" onmouseout="this.style.color='var(--color-text-muted)'">Analisis</a>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-subtle)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">{{ Str::limit($analysis->title, 30) }}</span>
</div>
@endsection

@section('content')

{{-- ── Header ── --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div>
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.375rem;">
            <h1 class="page-title" style="margin:0;">{{ $analysis->title }}</h1>
            @if($analysis->status === 'completed')
                <span class="badge badge-safe">Selesai</span>
            @elseif($analysis->status === 'running')
                <span class="badge badge-warning" id="status-badge" style="animation:pulse-orange 2s infinite;">Sedang Berjalan</span>
            @elseif($analysis->status === 'failed')
                <span class="badge badge-hate">Gagal</span>
            @else
                <span class="badge" style="background:var(--color-surface-2);color:var(--color-text-muted);">Antrian</span>
            @endif
        </div>
        <p class="page-subtitle">
            Platform: <strong>{{ strtoupper($analysis->platform) }}</strong> ·
            Mode: <strong>{{ ucfirst($analysis->search_mode) }}</strong> ·
            Max Links: <strong>{{ $analysis->max_links }}</strong> ·
            Oleh: <strong>{{ $analysis->user->name ?? '—' }}</strong> ·
            {{ $analysis->created_at->format('d M Y, H:i') }}
        </p>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem;">
        @if($analysis->status === 'completed')
        <a href="{{ route('analyses.export', $analysis->id) }}" class="btn btn-outline btn-sm" style="display:inline-flex;align-items:center;gap:0.375rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Unduh Dataset (CSV)
        </a>
        @endif
    </div>
</div>

{{-- ── Error Section ── --}}
@if($analysis->status === 'failed')
<div class="alert alert-danger" style="margin-bottom:1.25rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    <div>
        <p style="font-weight:600;margin-bottom:0.25rem;">Pipeline gagal dieksekusi</p>
        <p style="font-size:0.875rem;">
            {{ $analysis->error_message ?? 'Server AI Python tidak dapat dihubungi atau terjadi kesalahan tak terduga. Pastikan FastAPI berjalan di port 8080, lalu buat analisis baru.' }}
        </p>
    </div>
</div>
@endif


{{-- ── Statistik Cards (hanya jika completed) ── --}}
@if($analysis->status === 'completed' && $analysis->statistic)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.25rem;">

    <div class="metric-card">
        <div class="metric-card-icon" style="background:#EEF3F8;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-navy)" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div>
            <p class="metric-card-value">{{ number_format($analysis->statistic->total_data) }}</p>
            <p class="metric-card-label">Total Postingan</p>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-card-icon" style="background:var(--color-danger-bg);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-danger)" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
            <p class="metric-card-value" style="color:var(--color-danger);">{{ number_format($analysis->statistic->hate_speech_count) }}</p>
            <p class="metric-card-label">Hate Speech</p>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-card-icon" style="background:var(--color-teal-bg);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-teal)" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div>
            <p class="metric-card-value" style="color:var(--color-teal);">{{ number_format($analysis->statistic->non_hate_speech_count) }}</p>
            <p class="metric-card-label">Non-Hate Speech</p>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-card-icon" style="background:var(--color-primary-bg);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        </div>
        <div>
            <p class="metric-card-value" style="color:var(--color-primary);">{{ $analysis->statistic->hate_speech_pct }}<span style="font-size:1rem;">%</span></p>
            <p class="metric-card-label">% Hate Speech</p>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-card-icon" style="background:var(--color-secondary-bg);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-secondary-dark)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div>
            <p class="metric-card-value">{{ $analysis->execution_time_seconds ? round($analysis->execution_time_seconds) . 's' : '—' }}</p>
            <p class="metric-card-label">Waktu Eksekusi</p>
        </div>
    </div>

</div>

{{-- ── Keywords ── --}}
<div class="card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
    <span style="font-size:0.8125rem;font-weight:700;color:var(--color-text-muted);">Kata Kunci:</span>
    @foreach($analysis->keywords ?? [] as $kw)
    <span style="background:var(--color-primary-bg);border:1px solid rgba(231,111,81,0.25);color:var(--color-primary-dark);font-size:0.8125rem;font-weight:600;padding:0.25rem 0.75rem;border-radius:999px;">{{ $kw }}</span>
    @endforeach
</div>

{{-- ── Tabel Postingan ── --}}
<div class="table-wrapper">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
            <p style="font-size:1rem;font-weight:700;color:var(--color-navy);">Data Postingan</p>
            <p style="font-size:0.75rem;color:var(--color-text-muted);">Menampilkan postingan yang berhasil dikumpulkan dan diklasifikasikan.</p>
        </div>
        
        {{-- Filter Form --}}
        <form method="GET" action="{{ route('analyses.show', $analysis->id) }}" style="display:flex;align-items:center;gap:0.625rem;flex-wrap:wrap;">
            <div style="position:relative;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2" style="position:absolute;left:0.625rem;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari isi teks..." class="input" style="padding-left:2rem;font-size:0.8125rem;height:36px;width:180px;">
            </div>
            <select name="label_lvl1" class="input" style="width:auto;font-size:0.8125rem;height:36px;cursor:pointer;" onchange="this.form.submit()">
                <option value="all" {{ request('label_lvl1') === 'all' || !request('label_lvl1') ? 'selected' : '' }}>Semua Sentimen</option>
                <option value="hate_speech" {{ request('label_lvl1') === 'hate_speech' ? 'selected' : '' }}>Ujaran Kebencian (Hate)</option>
                <option value="non_hate_speech" {{ request('label_lvl1') === 'non_hate_speech' ? 'selected' : '' }}>Bukan Kebencian (Non-Hate)</option>
            </select>
            <select name="platform" class="input" style="width:auto;font-size:0.8125rem;height:36px;cursor:pointer;" onchange="this.form.submit()">
                <option value="all" {{ request('platform') === 'all' || !request('platform') ? 'selected' : '' }}>Semua Platform</option>
                <option value="X" {{ request('platform') === 'X' ? 'selected' : '' }}>Twitter (𝕏)</option>
                <option value="Threads" {{ request('platform') === 'Threads' ? 'selected' : '' }}>Threads</option>
            </select>
            <button type="submit" class="btn btn-navy btn-sm" style="height:36px;padding:0 0.875rem;">
                Filter
            </button>
            @if(request('search') || (request('label_lvl1') && request('label_lvl1') !== 'all') || (request('platform') && request('platform') !== 'all'))
            <a href="{{ route('analyses.show', $analysis->id) }}" class="btn btn-ghost btn-sm" style="height:36px;padding:0 0.625rem;" title="Reset Filter">
                ✕ Reset
            </a>
            @endif
        </form>
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:800px;">
            <thead style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
                <tr>
                    <th style="padding:0.625rem 1rem;font-size:0.725rem;font-weight:700;color:var(--color-text-muted);text-align:left;text-transform:uppercase;letter-spacing:0.05em;width:40%;">Konten Postingan</th>
                    <th style="padding:0.625rem 1rem;font-size:0.725rem;font-weight:700;color:var(--color-text-muted);text-align:center;text-transform:uppercase;letter-spacing:0.05em;">Platform</th>
                    <th style="padding:0.625rem 1rem;font-size:0.725rem;font-weight:700;color:var(--color-text-muted);text-align:center;text-transform:uppercase;letter-spacing:0.05em;">Sentimen L1</th>
                    <th style="padding:0.625rem 1rem;font-size:0.725rem;font-weight:700;color:var(--color-text-muted);text-align:center;text-transform:uppercase;letter-spacing:0.05em;">Kategori L2</th>
                    <th style="padding:0.625rem 1rem;font-size:0.725rem;font-weight:700;color:var(--color-text-muted);text-align:center;text-transform:uppercase;letter-spacing:0.05em;">Confidence</th>
                    <th style="padding:0.625rem 1rem;font-size:0.725rem;font-weight:700;color:var(--color-text-muted);text-align:left;text-transform:uppercase;letter-spacing:0.05em;">Author</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts ?? [] as $post)
                <tr style="border-bottom:1px solid var(--color-border);transition:background 0.15s;"
                    onmouseover="this.style.background='var(--color-surface-2)'"
                    onmouseout="this.style.background=''">
                    <td style="padding:0.875rem 1rem;">
                        <p style="font-size:0.8375rem;color:var(--color-text-body);line-height:1.6;">
                            {{ $post->classification->raw_content ?? '—' }}
                        </p>
                        @if($post->source_url)
                        <a href="{{ $post->source_url }}" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:3px;font-size:0.725rem;color:var(--color-primary);text-decoration:none;margin-top:4px;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                            <span>Buka URL Asli</span>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </a>
                        @endif
                    </td>
                    <td style="padding:0.875rem 1rem;text-align:center;">
                        @if(strtolower($post->platform) === 'x' || strtolower($post->platform) === 'twitter')
                            <span class="badge" style="background:#E7F3FA;color:#1DA1F2;font-size:0.725rem;">𝕏 Twitter</span>
                        @else
                            <span class="badge" style="background:#F0F0F0;color:#333;font-size:0.725rem;">⊙ Threads</span>
                        @endif
                    </td>
                    <td style="padding:0.875rem 1rem;text-align:center;">
                        @if(($post->classification->label_lvl1 ?? '') === 'hate_speech')
                            <span class="badge badge-hate">Hate</span>
                        @else
                            <span class="badge badge-safe">Non-Hate</span>
                        @endif
                    </td>
                    <td style="padding:0.875rem 1rem;text-align:center;">
                        <span style="font-size:0.75rem;font-weight:600;color:var(--color-navy);">
                            {{ str_replace('_', ' ', ucwords($post->classification->label_lvl2 ?? '—')) }}
                        </span>
                    </td>
                    <td style="padding:0.875rem 1rem;text-align:center;">
                        <span style="font-size:0.875rem;font-weight:800;color:var(--color-navy);">
                            {{ $post->classification ? round($post->classification->confidence_lvl1 * 100, 1) . '%' : '—' }}
                        </span>
                    </td>
                    <td style="padding:0.875rem 1rem;">
                        <p style="font-size:0.8125rem;font-weight:700;color:var(--color-navy);">{{ $post->author_username ? '@' . $post->author_username : 'anonim' }}</p>
                        <p style="font-size:0.75rem;color:var(--color-text-muted);">{{ $post->post_date ?? '—' }}</p>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding:3.5rem;text-align:center;">
                        <div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;">
                            <div style="width:44px;height:44px;border-radius:50%;background:var(--color-surface-2);display:flex;align-items:center;justify-content:center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            </div>
                            <p style="font-size:0.875rem;font-weight:700;color:var(--color-navy);">Tidak ada postingan yang sesuai filter</p>
                            <p style="font-size:0.8125rem;color:var(--color-text-muted);">Coba ubah kata kunci pencarian atau reset filter di atas.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination Footer --}}
    @if(isset($posts) && $posts->hasPages())
    <div style="padding:1rem 1.25rem;border-top:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
        <p style="font-size:0.8125rem;color:var(--color-text-muted);">
            Menampilkan {{ $posts->firstItem() }}–{{ $posts->lastItem() }} dari total {{ $posts->total() }} postingan
        </p>
        <div>
            {{ $posts->withQueryString()->links() }}
        </div>
    </div>
    @endif
</div>

@elseif($analysis->status === 'queued' || $analysis->status === 'running')
<div style="display:flex;flex-direction:column;gap:1.25rem;">

    {{-- Main Processing Card --}}
    <div class="card" style="padding:1.75rem;border-top:3.5px solid var(--color-primary);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid var(--color-border);">
            <div style="display:flex;align-items:center;gap:1rem;">
                <div style="width:48px;height:48px;border-radius:1rem;background:var(--color-primary-bg);display:flex;align-items:center;justify-content:center;position:relative;flex-shrink:0;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2.5" style="animation:spin 2.5s linear infinite;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </div>
                <div>
                    <h2 style="font-size:1.15rem;font-weight:800;color:var(--color-navy);margin:0 0 0.25rem 0;">Pemrosesan Pipeline AI Sedang Berjalan</h2>
                    <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;">Sistem sedang mengeksekusi pipeline end-to-end secara otomatis di server AI.</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;background:var(--color-surface-2);padding:0.4rem 0.85rem;border-radius:999px;border:1px solid var(--color-border);">
                <span style="width:8px;height:8px;border-radius:50%;background:#F39C12;animation:pulse-orange 1.5s infinite;"></span>
                <span style="font-size:0.75rem;font-weight:700;color:var(--color-navy);">Proses Latar Belakang</span>
            </div>
        </div>

        {{-- Progress Bar Animasi --}}
        <div style="margin-bottom:1.75rem;">
            <div class="progress-bar-track" style="height:6px;border-radius:999px;overflow:hidden;background:#E2E8F0;">
                <div class="progress-bar-fill indeterminate" style="height:6px;background:linear-gradient(90deg, var(--color-primary), var(--color-secondary));"></div>
            </div>
        </div>

        {{-- 2-Kolom: Parameter vs 5 Tahapan Stepper --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:1.5rem;">
            
            {{-- Kolom Kiri: Konfigurasi Parameter yang Sedang Diproses --}}
            <div style="background:var(--color-surface-2);border-radius:1rem;padding:1.25rem;display:flex;flex-direction:column;gap:1rem;border:1px solid var(--color-border);">
                <p style="font-size:0.8125rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-muted);margin:0;">
                    Parameter Analisis
                </p>

                {{-- Platform Target --}}
                <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:0.75rem;border-bottom:1px dashed var(--color-border);">
                    <span style="font-size:0.8125rem;color:var(--color-text-muted);">Platform Target</span>
                    @if($analysis->platform === 'x')
                        <span class="badge" style="background:#0F1419;color:#FFF;font-size:0.75rem;font-weight:700;padding:3px 8px;">𝕏 Twitter</span>
                    @elseif($analysis->platform === 'threads')
                        <span class="badge" style="background:#1E3A4C;color:#FFF;font-size:0.75rem;font-weight:700;padding:3px 8px;">Threads</span>
                    @else
                        <span class="badge badge-warning" style="font-size:0.75rem;font-weight:700;padding:3px 8px;">⚡ Dual (X + Threads)</span>
                    @endif
                </div>

                {{-- Kata Kunci --}}
                <div style="padding-bottom:0.75rem;border-bottom:1px dashed var(--color-border);">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.375rem;">
                        <span style="font-size:0.8125rem;color:var(--color-text-muted);">Kata Kunci Topik</span>
                        <span style="font-size:0.75rem;font-weight:700;color:var(--color-navy);">{{ count($analysis->keywords ?? []) }} Kata</span>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:0.375rem;">
                        @foreach($analysis->keywords ?? [] as $kw)
                            <span style="display:inline-flex;align-items:center;gap:3px;font-size:0.75rem;font-weight:700;background:#FFFFFF;border:1px solid var(--color-border);padding:2px 8px;border-radius:6px;color:var(--color-navy);">
                                #{{ $kw }}
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- Mode Pencarian & Limit --}}
                <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:0.75rem;border-bottom:1px dashed var(--color-border);">
                    <span style="font-size:0.8125rem;color:var(--color-text-muted);">Mode & Batas Link</span>
                    <span style="font-size:0.8125rem;font-weight:700;color:var(--color-navy);">
                        {{ ucfirst($analysis->search_mode) }} · Maks {{ $analysis->max_links }} URL
                    </span>
                </div>

                {{-- Mode Browser --}}
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.8125rem;color:var(--color-text-muted);">Mode Engine</span>
                    <span style="font-size:0.8125rem;font-weight:700;color:var(--color-navy);">
                        {{ $analysis->headless ? 'Headless Browser (Efisien)' : 'Visual Browser (GUI)' }}
                    </span>
                </div>
            </div>

            {{-- Kolom Kanan: 5 Tahapan Alur Kerja (Pipeline Stepper) --}}
            <div style="display:flex;flex-direction:column;gap:0.625rem;">
                <p style="font-size:0.8125rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-muted);margin:0 0 0.25rem 0;">
                    Tahapan Alur Kerja (Pipeline)
                </p>

                {{-- Step 1 --}}
                <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.625rem 0.875rem;background:#FFFFFF;border:1px solid var(--color-border);border-radius:0.75rem;">
                    <div style="width:22px;height:22px;border-radius:50%;background:#D5F5E3;color:#27AE60;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:800;flex-shrink:0;margin-top:2px;">
                        ✓
                    </div>
                    <div>
                        <p style="font-size:0.8125rem;font-weight:700;color:var(--color-navy);margin:0;">1. Validasi Sesi & Engine</p>
                        <p style="font-size:0.75rem;color:var(--color-text-muted);margin:2px 0 0 0;">Browser Playwright Persistent Context siap.</p>
                    </div>
                </div>

                {{-- Step 2 (Active) --}}
                <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.625rem 0.875rem;background:var(--color-primary-bg);border:1.5px solid var(--color-primary);border-radius:0.75rem;box-shadow:0 2px 8px rgba(231,111,81,0.12);">
                    <div style="width:22px;height:22px;border-radius:50%;background:var(--color-primary);color:#FFF;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:800;flex-shrink:0;margin-top:2px;animation:pulse-orange 1.5s infinite;">
                        2
                    </div>
                    <div>
                        <div style="display:flex;align-items:center;gap:0.375rem;">
                            <p style="font-size:0.8125rem;font-weight:800;color:var(--color-navy);margin:0;">2. Scraping & Deep-Crawl</p>
                            <span style="font-size:0.625rem;font-weight:800;color:var(--color-primary);background:#FFF;padding:1px 6px;border-radius:4px;border:1px solid var(--color-primary);">AKTIF</span>
                        </div>
                        <p style="font-size:0.75rem;color:var(--color-text-body);margin:2px 0 0 0;">Mengumpulkan postingan target, reply, dan komentar publik.</p>
                    </div>
                </div>

                {{-- Step 3 --}}
                <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.625rem 0.875rem;background:var(--color-surface-2);border:1px solid var(--color-border);border-radius:0.75rem;opacity:0.85;">
                    <div style="width:22px;height:22px;border-radius:50%;background:#E2E8F0;color:var(--color-text-muted);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:800;flex-shrink:0;margin-top:2px;">
                        3
                    </div>
                    <div>
                        <p style="font-size:0.8125rem;font-weight:700;color:var(--color-navy);margin:0;">3. Text Preprocessing</p>
                        <p style="font-size:0.75rem;color:var(--color-text-muted);margin:2px 0 0 0;">Normalisasi slang (15.167 kata), emoji & pembersihan teks.</p>
                    </div>
                </div>

                {{-- Step 4 --}}
                <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.625rem 0.875rem;background:var(--color-surface-2);border:1px solid var(--color-border);border-radius:0.75rem;opacity:0.85;">
                    <div style="width:22px;height:22px;border-radius:50%;background:#E2E8F0;color:var(--color-text-muted);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:800;flex-shrink:0;margin-top:2px;">
                        4
                    </div>
                    <div>
                        <p style="font-size:0.8125rem;font-weight:700;color:var(--color-navy);margin:0;">4. Klasifikasi IndoBERT</p>
                        <p style="font-size:0.75rem;color:var(--color-text-muted);margin:2px 0 0 0;">Prediksi hierarkis Level 1 (Hate Speech) & Level 2 (Kategori Target).</p>
                    </div>
                </div>

                {{-- Step 5 --}}
                <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.625rem 0.875rem;background:var(--color-surface-2);border:1px solid var(--color-border);border-radius:0.75rem;opacity:0.85;">
                    <div style="width:22px;height:22px;border-radius:50%;background:#E2E8F0;color:var(--color-text-muted);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:800;flex-shrink:0;margin-top:2px;">
                        5
                    </div>
                    <div>
                        <p style="font-size:0.8125rem;font-weight:700;color:var(--color-navy);margin:0;">5. Kalkulasi Agregasi & Visualisasi</p>
                        <p style="font-size:0.75rem;color:var(--color-text-muted);margin:2px 0 0 0;">Menghitung persentase metrik dan memuat dashboard hasil.</p>
                    </div>
                </div>

            </div>

        </div>

        {{-- Educational / Status Footer --}}
        <div style="margin-top:1.5rem;padding:0.875rem 1.125rem;background:#F8FAFC;border:1px solid var(--color-border);border-radius:0.75rem;display:flex;align-items:center;gap:0.75rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:0;line-height:1.5;">
                <strong style="color:var(--color-navy);">Informasi:</strong> Proses ini membutuhkan waktu sekitar 1–3 menit tergantung jumlah link. Halaman ini akan <strong>otomatis memuat seluruh tabel dan grafik hasil analisis</strong> saat seluruh tahapan selesai.
            </p>
        </div>

    </div>

</div>
@endif

@endsection

@if($analysis->status === 'running' || $analysis->status === 'queued')
@push('scripts')
<script>
    // Refresh otomatis di background setiap 8 detik hingga analisis selesai
    setTimeout(function () {
        window.location.reload();
    }, 8000);
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
@endif

