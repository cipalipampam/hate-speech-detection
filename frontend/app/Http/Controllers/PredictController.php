<?php

namespace App\Http\Controllers;

use App\Http\Requests\Prediction\SinglePredictRequest;
use App\Services\SinglePredictService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PredictController extends Controller
{
    public function __construct(
        protected SinglePredictService $predictService
    ) {}

    public function index(): View
    {
        return view('predict.index');
    }

    /**
     * Endpoint JSON untuk Live Text Classifier (Alpine.js fetch).
     */
    public function classify(SinglePredictRequest $request): JsonResponse
    {
        $result = $this->predictService->predict($request->validated('text'));

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Server AI tidak merespons. Pastikan FastAPI sedang berjalan.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
        ]);
    }
}
