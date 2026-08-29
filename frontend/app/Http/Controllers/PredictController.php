<?php

namespace App\Http\Controllers;

use App\Services\SinglePredictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function classify(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $result = $this->predictService->predict($request->input('text'));

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
