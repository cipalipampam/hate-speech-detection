<?php

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PredictController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScraperMonitorController;
use Illuminate\Support\Facades\Route;

// ─── Public / Guest Routes ────────────────────────────────────────────────────

Route::get('/', fn () => view('welcome'))->name('welcome');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// ─── Authenticated Routes ─────────────────────────────────────────────────────

Route::middleware(['auth'])->group(function () {

    // § 01.0 System Overview (URL: /overview)
    Route::get('/overview', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', fn () => redirect()->route('dashboard')); // Backward compatibility alias

    // § 02.0 Batch Analyses (URL: /analyses)
    Route::prefix('analyses')->name('analyses.')->group(function () {
        Route::get('/', [AnalysisController::class, 'index'])->name('index');
        Route::get('/create', [AnalysisController::class, 'create'])->name('create')->middleware('can:run-analysis');
        Route::get('/new', fn () => redirect()->route('analyses.create')); // Backward compatibility alias
        Route::post('/', [AnalysisController::class, 'store'])->name('store')->middleware('can:run-analysis');
        Route::get('/{analysis}', [AnalysisController::class, 'show'])->name('show');
        Route::get('/{analysis}/export', [AnalysisController::class, 'exportCsv'])
            ->name('export')
            ->middleware('can:export-reports');
    });

    // § 03.0 Interactive Live Text Classifier (URL: /classifier)
    Route::prefix('classifier')->name('predict.')->middleware('can:test-single-prediction')->group(function () {
        Route::get('/', [PredictController::class, 'index'])->name('index');
        Route::post('/classify', [PredictController::class, 'classify'])->name('classify');
    });
    Route::get('/predict', fn () => redirect()->route('predict.index')); // Backward compatibility alias
    Route::post('/predict/classify', [PredictController::class, 'classify'])->middleware('can:test-single-prediction');

    // § 04.0 Infrastructure & Session Telemetry (URL: /telemetry)
    Route::get('/telemetry', [ScraperMonitorController::class, 'status'])->name('scraper.status');
    Route::get('/scraper/status', fn () => redirect()->route('scraper.status')); // Backward compatibility alias
    Route::prefix('scraper')->name('scraper.')->group(function () {
        Route::post('/login-trigger/{platform}', [ScraperMonitorController::class, 'triggerLogin'])->name('login-trigger')->middleware('can:manage-auth-sessions');
    });

    // Telemetry Health API (Masthead Header)
    Route::get('/api/fastapi/health', [ScraperMonitorController::class, 'health'])->name('fastapi.health');

    // § 00.1 Researcher Profile & Security Settings (URL: /profile/edit)
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/info', [ProfileController::class, 'updateInfo'])->name('update');
        Route::patch('/password', [ProfileController::class, 'updatePassword'])->name('password');
    });

    // § 05.0 User Directory & Personnel Management (URL: /user-directory)
    Route::prefix('user-directory')->name('admin.users.')->middleware('can:manage-users')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::patch('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::patch('/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('toggle-status');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
    });
    // Backward compatibility redirects for legacy /admin/users URLs
    Route::get('/admin/users', fn () => redirect()->route('admin.users.index'))->middleware('can:manage-users');
    Route::get('/users', fn () => redirect()->route('admin.users.index'))->middleware('can:manage-users');

});
