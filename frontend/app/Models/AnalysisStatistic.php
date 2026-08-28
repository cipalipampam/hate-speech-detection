<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model: AnalysisStatistic
 *
 * Tanggung Jawab: Ringkasan metrik agregasi sentimen per sesi analisis.
 * Satu baris per satu sesi analisis (one-to-one dengan analyses).
 * Di-insert hanya sekali saat analisis selesai (status: completed).
 */
class AnalysisStatistic extends Model
{
    use HasFactory;

    protected $table = 'analysis_statistics';

    protected $fillable = [
        'analysis_id',
        'total_data',
        'hate_speech_count',
        'non_hate_speech_count',
        'hate_speech_pct',
        'avg_confidence_lvl1',
        'avg_confidence_lvl2',
        'level2_breakdown',
        'platform_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'total_data'            => 'integer',
            'hate_speech_count'     => 'integer',
            'non_hate_speech_count' => 'integer',
            'hate_speech_pct'       => 'float',
            'avg_confidence_lvl1'   => 'float',
            'avg_confidence_lvl2'   => 'float',
            'level2_breakdown'      => 'array',
            'platform_breakdown'    => 'array',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    /** Sesi analisis induk. */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class, 'analysis_id');
    }
}
