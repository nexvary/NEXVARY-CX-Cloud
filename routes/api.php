<?php

use App\Http\Controllers\API\MobileController;
use App\Http\Controllers\API\StatusController;
use Illuminate\Support\Facades\Route;

// ── Public health / status ────────────────────────────────────────────────
Route::get('/health', [StatusController::class, 'health'])->name('api.health');
Route::get('/pbx/status', [StatusController::class, 'pbxStatus'])->name('api.pbx_status');

// ── Authenticated API (Sanctum) ───────────────────────────────────────────
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', fn(\Illuminate\Http\Request $r) => $r->user())->name('api.user');

    // Real-time dashboard metrics
    Route::get('/metrics/live', [StatusController::class, 'liveMetrics'])->name('api.metrics.live');

    // Active calls JSON
    Route::get('/calls/active', [StatusController::class, 'activeCalls'])->name('api.calls.active');
});

// ── Mobile API v1 (NEXVARY CX Manager Android) ───────────────────────────
Route::prefix('mobile/v1')->name('mobile.')->middleware(['auth:sanctum'])->group(function () {
    // Server and service health
    Route::get('/status',      [MobileController::class, 'serverStatus'])->name('status');
    Route::get('/alerts',      [MobileController::class, 'alerts'])->name('alerts');

    // PBX
    Route::get('/extensions',  [MobileController::class, 'extensions'])->name('extensions');
    Route::get('/calls/active',[MobileController::class, 'activeCalls'])->name('calls.active');
    Route::get('/calls',       [MobileController::class, 'callHistory'])->name('calls');

    // SMS
    Route::get('/sms',                                          [MobileController::class, 'smsInbox'])->name('sms');
    Route::get('/sms/{conversation}',                           [MobileController::class, 'smsMessages'])->name('sms.messages');

    // Device pairing (admin ability required)
    Route::post('/pair',       [MobileController::class, 'generatePairingQr'])->name('pair');
});
