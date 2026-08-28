<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model: AnalysisPost
 *
 * Tanggung Jawab: Metadata postingan media sosial (statis, ringan).
 * Tidak menyimpan konten teks — ada di AnalysisClassification (one-to-one).
 *
 * Digunakan saat:
 * - Menampilkan tabel daftar postingan (platform, author, URL, tipe, tanggal).
 * - Filter berdasarkan platform atau author_username.
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
