<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model: AnalysisClassification
 *
 * Tanggung Jawab: Konten teks (raw & clean) dan hasil inferensi IndoBERT.
 * One-to-one dengan AnalysisPost.
 *
 * Digunakan saat:
 * - Menampilkan konten teks lengkap dari postingan.
 * - Filter berdasarkan label sentimen (hate/non-hate, sub-kategori Level 2).
 * - Menampilkan confidence score dan distribusi probabilitas.
 */
class AnalysisClassification extends Model
{
    use HasFactory;

    protected $table = 'analysis_classifications';

    protected $fillable = [
        'post_id',
        'raw_content',
        'clean_content',
        'label_lvl1',
        'confidence_lvl1',
        'probabilities_lvl1',
        'label_lvl2',
        'confidence_lvl2',
        'probabilities_lvl2',
    ];

    protected function casts(): array
    {
        return [
            'confidence_lvl1'    => 'float',
            'confidence_lvl2'    => 'float',
            'probabilities_lvl1' => 'array',
            'probabilities_lvl2' => 'array',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    /** Postingan media sosial yang diklasifikasi ini. */
    public function post(): BelongsTo
    {
        return $this->belongsTo(AnalysisPost::class, 'post_id');
    }
}
