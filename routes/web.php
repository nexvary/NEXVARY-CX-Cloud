<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Install\WizardController;
use Illuminate\Support\Facades\Route;

// ── Setup Wizard (pre-install) ────────────────────────────────────────────
Route::prefix('setup')->name('install.')->middleware([\App\Http\Middleware\ProtectSetup::class, 'throttle:45,1'])->group(function () {
    Route::get('/', [WizardController::class, 'index'])->name('index');
    Route::post('/readiness', [WizardController::class, 'readiness'])->name('readiness');
    Route::post('/connection/{service}', [WizardController::class, 'connection'])
        ->whereIn('service', ['database', 'redis', 'freeswitch'])->name('connection');
    Route::post('/complete', [WizardController::class, 'complete'])->name('complete');
});

// ── Auth (accessible before install too) ─────────────────────────────────
Route::middleware(['installed'])->group(function () {
    Route::get('/login',  [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:8,1')->name('login.post');
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

    // MFA
    Route::get('/mfa',  [MfaController::class, 'show'])->middleware('auth')->name('mfa');
    Route::post('/mfa', [MfaController::class, 'verify'])->middleware(['auth', 'throttle:6,1'])->name('mfa.verify');
});

// ── Authenticated application ─────────────────────────────────────────────
Route::middleware(['installed', 'auth', \App\Http\Middleware\RequireVerifiedSession::class])->group(function () {

    // Dashboard
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');

    Route::middleware('nexvary.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\GuideController::class, 'index'])->name('index');
        Route::get('/{module}', [\App\Http\Controllers\Admin\GuideController::class, 'show'])->name('show');
        Route::post('/contacts', [\App\Http\Controllers\Admin\GuideController::class, 'storeContact'])->name('contacts.store');
        Route::post('/{module}/draft', [\App\Http\Controllers\Admin\ConfigurationController::class, 'store'])->name('draft.store');
    });

    Route::redirect('/pbx/extensions', '/admin/extensions')->name('pbx.extensions.index');
    Route::redirect('/pbx/trunks', '/admin/trunks')->name('pbx.trunks.index');
});
