<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model: Analysis
 *
 * Tanggung Jawab: Konfigurasi & status job saja.
 * Baca agregasi metrik → via $analysis->statistic
 * Baca postingan detail → via $analysis->posts
 * Baca file ekspor      → via $analysis->exports
 */
class Analysis extends Model
{
    use HasFactory;

    protected $table = 'analyses';

    protected $fillable = [
        'user_id',
        'job_id',
        'title',
        'platform',
        'keywords',
        'search_mode',
        'max_links',
        'max_scroll_steps',
        'headless',
        'status',
        'execution_time_seconds',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'keywords'               => 'array',
            'max_links'              => 'integer',
            'max_scroll_steps'       => 'integer',
            'headless'               => 'boolean',
            'execution_time_seconds' => 'float',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    /** User pemilik sesi analisis ini. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Ringkasan metrik agregasi sentimen (one-to-one). */
    public function statistic(): HasOne
    {
        return $this->hasOne(AnalysisStatistic::class, 'analysis_id');
    }

    /** Semua postingan media sosial dalam sesi ini. */
    public function posts(): HasMany
    {
        return $this->hasMany(AnalysisPost::class, 'analysis_id');
    }

    /** File-file hasil ekspor dari sesi ini. */
    public function exports(): HasMany
    {
        return $this->hasMany(AnalysisExport::class, 'analysis_id');
    }
}
