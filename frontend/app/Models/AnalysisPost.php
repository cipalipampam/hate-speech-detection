<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Metadata postingan medsos (platform, author, URL, tanggal); konten teks ada di AnalysisClassification.
 */
class AnalysisPost extends Model
{
    use HasFactory;

    protected $table = 'analysis_posts';

    protected $fillable = [
        'analysis_id',
        'platform',
        'source_url',
        'author_username',
        'post_type',
        'post_date',
    ];

    // ─── Relasi ──────────────────────────────────────────────────────────────

    /** Sesi analisis induk. */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class, 'analysis_id');
    }

    /** Hasil klasifikasi IndoBERT untuk postingan ini (one-to-one). */
    public function classification(): HasOne
    {
        return $this->hasOne(AnalysisClassification::class, 'post_id');
    }
}
