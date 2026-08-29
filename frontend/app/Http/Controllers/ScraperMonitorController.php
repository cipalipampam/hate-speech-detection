<?php

namespace App\Http\Controllers;

use App\Services\FastAPIClientService;
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
     */
    public function status(): View
    {
        $statusResult = $this->fastApiClient->getAuthStatus();
        $isServerOnline = $statusResult['success'];
        $sessionData = $statusResult['data'] ?? null;

        return view('scraper.status', compact('isServerOnline', 'sessionData', 'statusResult'));
    }

    /**
     * Memicu proses login interaktif Playwright di server Python.
     */
    public function triggerLogin(string $platform): RedirectResponse
    {
        if (!in_array(strtolower($platform), ['x', 'threads'])) {
            return back()->with('error', 'Platform tidak valid. Pilih X atau Threads.');
        }

        $result = $this->fastApiClient->triggerLogin(strtolower($platform));

        if (!$result['success']) {
            return back()->with('error', $result['message'] ?? 'Gagal memicu proses login.');
        }

        $platformName = strtolower($platform) === 'x' ? 'Twitter (X)' : 'Threads';

        return back()->with('success', "Proses login {$platformName} berhasil diinisiasi. Silakan selesaikan login pada jendela browser yang terbuka.");
    }
}
