<?php

namespace App\Services;

use App\Models\SinglePrediction;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Service: SinglePredictService
 *
 * Tanggung Jawab: Inferensi teks tunggal via FastAPI & pencatatan ke database.
 */
class SinglePredictService
{
    public function __construct(
        protected FastAPIClientService $fastApiClient
    ) {}

    /**
     * Kirim teks ke FastAPI untuk diklasifikasi, simpan hasilnya ke single_predictions.
     */
    public function predictAndSave(string $text, ?int $userId = null, bool $preprocess = true): array
    {
        $apiResult = $this->fastApiClient->classifySingle($text, $preprocess);

        if (!$apiResult['success']) {
            return [
                'success' => false,
                'message' => $apiResult['message'] ?? 'Gagal melakukan klasifikasi teks.',
            ];
        }

        $res  = $apiResult['data'];
        $lvl1 = $res['level1'] ?? [];
        $lvl2 = $res['level2'] ?? [];

        $record = SinglePrediction::create([
            'user_id'            => $userId,
            'input_text'         => $text,
            'clean_text'         => $res['text'] ?? $text,
            'label_lvl1'         => $lvl1['label'] ?? 'non_hate_speech',
            'label_lvl2'         => $lvl2['label'] ?? 'tidak_relevan',
            'confidence_lvl1'    => (float) ($lvl1['confidence'] ?? 0.0),
            'confidence_lvl2'    => (float) ($lvl2['confidence'] ?? 0.0),
            'probabilities_lvl1' => $lvl1['probabilities'] ?? null,
            'probabilities_lvl2' => $lvl2['probabilities'] ?? null,
        ]);

        return [
            'success' => true,
            'data'    => [
                'id'             => $record->id,
                'input_text'     => $record->input_text,
                'clean_text'     => $record->clean_text,
                'is_hate_speech' => ($record->label_lvl1 === 'hate_speech'),
                'level1'         => $lvl1,
                'level2'         => $lvl2,
                'created_at'     => $record->created_at->format('d M Y, H:i'),
            ],
        ];
    }

    /**
     * Riwayat pengujian teks terbaru (opsional filter per user).
     */
    public function getRecentPredictions(?int $userId = null, int $limit = 20)
    {
        $query = SinglePrediction::with('user')->latest();
        if ($userId) {
            $query->where('user_id', $userId);
        }
        return $query->take($limit)->get();
    }
}
