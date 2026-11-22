<?php

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
