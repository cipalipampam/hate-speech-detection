<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model: AnalysisExport
 *
 * Tanggung Jawab: Metadata file hasil ekspor (CSV/Excel/PDF) per sesi analisis.
 * Satu analisis bisa memiliki lebih dari satu file ekspor.
 *
 * Digunakan saat:
 * - Menampilkan daftar file yang tersedia untuk diunduh.
 * - Manajemen file (hapus, regenerasi) tanpa menyentuh tabel analyses.
 */
class AnalysisExport extends Model
{
    use HasFactory;

    protected $table = 'analysis_exports';

    protected $fillable = [
        'analysis_id',
        'filename',
        'disk',
        'path',
        'format',
        'size_bytes',
        'mime_type',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    /** Sesi analisis induk. */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class, 'analysis_id');
    }

    // ─── Computed Attributes ─────────────────────────────────────────────────

    /** Ukuran file dalam format manusia (KB / MB). */
    public function getReadableSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2) . ' MB';
        }
        if ($bytes >= 1_024) {
            return round($bytes / 1_024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
