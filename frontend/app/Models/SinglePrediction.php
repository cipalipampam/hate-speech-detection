<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model: SinglePrediction
 *
 * Tanggung Jawab: Riwayat pengujian kalimat teks tunggal oleh pengguna.
 * Mandiri, tidak bergantung pada sesi analisis penuh.
 */
class SinglePrediction extends Model
{
    use HasFactory;

    protected $table = 'single_predictions';

    protected $fillable = [
        'user_id',
        'input_text',
        'clean_text',
        'label_lvl1',
        'label_lvl2',
        'confidence_lvl1',
        'confidence_lvl2',
        'probabilities_lvl1',
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

    /** User yang melakukan pengujian teks ini. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
