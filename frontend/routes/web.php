<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\PredictController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

// ─── Public / Guest Routes ────────────────────────────────────────────────────

Route::get('/', fn() => view('welcome'))->name('welcome');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// ─── Authenticated Routes ─────────────────────────────────────────────────────

Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Analisis ────────────────────────────────────────────────────────────────
    Route::prefix('analyses')->name('analyses.')->group(function () {
        Route::get('/',                  [AnalysisController::class, 'index'])->name('index');
        Route::get('/new',               [AnalysisController::class, 'create'])->name('create')->middleware('can:run-analysis');
        Route::post('/',                 [AnalysisController::class, 'store'])->name('store')->middleware('can:run-analysis');
        Route::get('/{analysis}',        [AnalysisController::class, 'show'])->name('show');
        Route::get('/{analysis}/export', [AnalysisController::class, 'exportCsv'])->name('export');
    });

    // ── Live Text Classifier ───────────────────────────────────────────────────
    Route::prefix('predict')->name('predict.')->middleware('can:test-single-prediction')->group(function () {
        Route::get('/',      [PredictController::class, 'index'])->name('index');
        Route::post('/classify', [PredictController::class, 'classify'])->name('classify');
    });

    // ── Monitor Scraper ───────────────────────────────────────────────────────
    Route::prefix('scraper')->name('scraper.')->group(function () {
        Route::get('/status',                   [\App\Http\Controllers\ScraperMonitorController::class, 'status'])->name('status');
        Route::post('/login-trigger/{platform}', [\App\Http\Controllers\ScraperMonitorController::class, 'triggerLogin'])->name('login-trigger')->middleware('can:manage-auth-sessions');
    });

    // ── Profil ────────────────────────────────────────────────────────────────
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/edit',    [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/info',  [ProfileController::class, 'updateInfo'])->name('update');
        Route::patch('/password', [ProfileController::class, 'updatePassword'])->name('password');
    });

    // ── Admin ─────────────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->middleware('can:manage-users')->group(function () {
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',             [UserManagementController::class, 'index'])->name('index');
            Route::get('/create',       [UserManagementController::class, 'create'])->name('create');
            Route::post('/',            [UserManagementController::class, 'store'])->name('store');
            Route::get('/{user}/edit',  [UserManagementController::class, 'edit'])->name('edit');
            Route::patch('/{user}',     [UserManagementController::class, 'update'])->name('update');
            Route::patch('/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('toggle-status');
        });
    });

});
