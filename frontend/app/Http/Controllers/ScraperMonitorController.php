<?php

namespace App\Http\Controllers;

use App\Http\Requests\Scraper\TriggerLoginRequest;
use App\Services\FastAPIClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScraperMonitorController extends Controller
{
    public function __construct(
        protected FastAPIClientService $fastApiClient
    ) {}

    /**
     * Tampilkan halaman status dan monitor sesi scraper (X & Threads).
     * Mendukung respons JSON untuk pembaruan telemetri dinamis (real-time).
     */
    public function status(Request $request): View|JsonResponse
    {
        $telemetry = $this->fastApiClient->getTelemetryData();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(array_merge(['success' => true], $telemetry));
        }

        return view('scraper.status', $telemetry);
    }

    /**
     * Endpoint kesehatan singkat untuk pill telemetri masthead di header seluruh halaman.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'online' => $this->fastApiClient->checkHealth(),
        ]);
    }

    /**
     * Memicu proses login interaktif Playwright di server Python.
     */
    public function triggerLogin(TriggerLoginRequest $request): RedirectResponse
    {
        $platform     = $request->getPlatform();
        $platformName = $request->getPlatformDisplayName();

        $result = $this->fastApiClient->triggerLogin($platform);

        if (!$result['success']) {
            return back()->with('error', $result['message'] ?? 'Gagal memicu proses login.');
        }

        return back()->with('success', "Proses login {$platformName} berhasil diinisiasi. Silakan selesaikan login pada jendela browser yang terbuka.");
    }
}
