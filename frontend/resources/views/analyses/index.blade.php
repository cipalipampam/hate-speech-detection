@extends('layouts.app')

@section('title', 'Riwayat Analisis')

@section('breadcrumb')
<div style="display:flex;align-items:center;gap:0.5rem;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    <span style="font-size:0.875rem;font-weight:600;color:var(--color-text-muted);">Riwayat Analisis</span>
</div>
@endsection

@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 class="page-title">Riwayat Analisis</h1>
        <p class="page-subtitle">Semua sesi analisis ujaran kebencian yang pernah dijalankan.</p>
    </div>
    @can('run-analysis')
    <a href="{{ route('analyses.create') }}" class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Analisis Baru
    </a>
    @endcan
</div>

{{-- Filter Bar --}}
<div class="card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;">
    <form method="GET" action="{{ route('analyses.index') }}" style="display:flex;align-items:center;gap:0.875rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:220px;position:relative;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2" style="position:absolute;left:0.875rem;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari judul analisis atau kata kunci..." class="input" style="padding-left:2.35rem;font-size:0.875rem;height:38px;">
        </div>
        
        <select name="platform" class="input" style="width:auto;font-size:0.875rem;height:38px;cursor:pointer;" onchange="this.form.submit()">
            <option value="all" {{ request('platform') === 'all' || !request('platform') ? 'selected' : '' }}>Semua Platform</option>
            <option value="x" {{ request('platform') === 'x' ? 'selected' : '' }}>Twitter (𝕏)</option>
            <option value="threads" {{ request('platform') === 'threads' ? 'selected' : '' }}>Threads</option>
            <option value="both" {{ request('platform') === 'both' ? 'selected' : '' }}>Keduanya (Both)</option>
        </select>

        <select name="status" class="input" style="width:auto;font-size:0.875rem;height:38px;cursor:pointer;" onchange="this.form.submit()">
            <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>Semua Status</option>
            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Selesai</option>
            <option value="running" {{ request('status') === 'running' ? 'selected' : '' }}>Sedang Berjalan</option>
            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Gagal</option>
            <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>Antrian</option>
        </select>

        <button type="submit" class="btn btn-navy btn-sm" style="height:38px;padding:0 1rem;">
            Filter
        </button>

        @if(request('search') || (request('platform') && request('platform') !== 'all') || (request('status') && request('status') !== 'all'))
        <a href="{{ route('analyses.index') }}" class="btn btn-ghost btn-sm" style="height:38px;padding:0 0.75rem;" title="Reset Filter">
            ✕ Reset
        </a>
        @endif
    </form>
</div>

{{-- Table --}}
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th style="width:36px;">#</th>
                <th>Judul Analisis</th>
                <th>Platform</th>
                <th>Kata Kunci</th>
                <th style="text-align:center;">Total Data</th>
                <th style="text-align:center;">Hate %</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:center;">Waktu</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($analyses ?? [] as $analysis)
            <tr>
                <td style="color:var(--color-text-subtle);font-size:0.8125rem;">
                    {{ ($analyses->currentPage() - 1) * $analyses->perPage() + $loop->iteration }}
                </td>
                <td>
                    <a href="{{ route('analyses.show', $analysis->id) }}"
                       style="font-weight:700;color:var(--color-navy);text-decoration:none;display:block;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                       onmouseover="this.style.color='var(--color-primary)'"
                       onmouseout="this.style.color='var(--color-navy)'">
                        {{ $analysis->title }}
                    </a>
                    <p style="font-size:0.75rem;color:var(--color-text-muted);margin-top:1px;">{{ $analysis->user->name ?? '—' }} · {{ $analysis->created_at->format('d M Y, H:i') }}</p>
                </td>
                <td>
                    @if(strtolower($analysis->platform) === 'x')
                        <span class="badge" style="background:#E7F3FA;color:#1DA1F2;font-size:0.75rem;">𝕏 Twitter</span>
                    @elseif(strtolower($analysis->platform) === 'threads')
                        <span class="badge" style="background:#F0F0F0;color:#333;font-size:0.75rem;">⊙ Threads</span>
                    @else
                        <span class="badge" style="background:var(--color-primary-bg);color:var(--color-primary-dark);font-size:0.75rem;">⚡ Dual</span>
                    @endif
                </td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;max-width:160px;">
                        @foreach(array_slice($analysis->keywords ?? [], 0, 2) as $kw)
                        <span style="background:var(--color-surface-2);border:1px solid var(--color-border);border-radius:999px;padding:2px 8px;font-size:0.7rem;font-weight:600;color:var(--color-text-body);">{{ $kw }}</span>
                        @endforeach
                        @if(count($analysis->keywords ?? []) > 2)
                        <span style="font-size:0.7rem;color:var(--color-text-muted);">+{{ count($analysis->keywords) - 2 }}</span>
                        @endif
                    </div>
                </td>
                <td style="text-align:center;font-weight:800;color:var(--color-navy);">
                    {{ $analysis->statistic ? number_format($analysis->statistic->total_data) : '—' }}
                </td>
                <td style="text-align:center;">
                    @if($analysis->statistic)
                    <span style="font-weight:800;color:{{ $analysis->statistic->hate_speech_pct > 50 ? 'var(--color-danger)' : 'var(--color-teal)' }};">
                        {{ $analysis->statistic->hate_speech_pct }}%
                    </span>
                    @else
                    <span style="color:var(--color-text-subtle);">—</span>
                    @endif
                </td>
                <td style="text-align:center;">
                    @if($analysis->status === 'completed')
                        <span class="badge badge-safe">Selesai</span>
                    @elseif($analysis->status === 'running')
                        <span class="badge badge-warning">Berjalan</span>
                    @elseif($analysis->status === 'failed')
                        <span class="badge badge-hate">Gagal</span>
                    @else
                        <span class="badge" style="background:var(--color-surface-2);color:var(--color-text-muted);">Antrian</span>
                    @endif
                </td>
                <td style="text-align:center;font-size:0.8125rem;color:var(--color-text-muted);">
                    {{ $analysis->execution_time_seconds ? round($analysis->execution_time_seconds) . 's' : '—' }}
                </td>
                <td style="text-align:right;">
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.375rem;">
                        <a href="{{ route('analyses.show', $analysis->id) }}" class="btn btn-ghost btn-sm" style="padding:0.35rem 0.625rem;" title="Lihat Detail">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                        @if($analysis->status === 'completed')
                        <a href="{{ route('analyses.export', $analysis->id) }}" class="btn btn-ghost btn-sm" style="padding:0.35rem 0.625rem;color:var(--color-teal);" title="Unduh Dataset (CSV)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </a>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="padding:3.5rem 1rem;text-align:center;">
                    <div style="display:flex;flex-direction:column;align-items:center;gap:0.875rem;">
                        <div style="width:56px;height:56px;border-radius:50%;background:var(--color-surface-2);display:flex;align-items:center;justify-content:center;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div>
                            <p style="font-weight:700;color:var(--color-navy);margin-bottom:0.25rem;">Tidak ada analisis yang ditemukan</p>
                            <p style="font-size:0.875rem;color:var(--color-text-muted);">Coba ubah kata kunci filter atau mulai analisis baru.</p>
                        </div>
                        @can('run-analysis')
                        <a href="{{ route('analyses.create') }}" class="btn btn-primary btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            Mulai Analisis Baru
                        </a>
                        @endcan
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Pagination Footer --}}
@if(isset($analyses) && $analyses->hasPages())
<div style="margin-top:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
    <p style="font-size:0.8125rem;color:var(--color-text-muted);">
        Menampilkan {{ $analyses->firstItem() }}–{{ $analyses->lastItem() }} dari total {{ $analyses->total() }} sesi
    </p>
    <div>
        {{ $analyses->withQueryString()->links() }}
    </div>
</div>
@endif

@endsection
