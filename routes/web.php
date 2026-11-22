<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Billing\BillingAccountController;
use App\Http\Controllers\Billing\RateTableController;
use App\Http\Controllers\CRM\ContactController;
use App\Http\Controllers\CRM\TicketController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Install\WizardController;
use App\Http\Controllers\PBX\CallRecordController;
use App\Http\Controllers\Server\ServerController;
use App\Http\Controllers\SMS\SmsController;
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

    // ── Call Center ───────────────────────────────────────────────────────

    Route::prefix('call-center')->name('call_center.')->group(function () {
        Route::get('/live',       [CallRecordController::class, 'live'])->name('live');
        Route::delete('/live/{uuid}', [CallRecordController::class, 'hangup'])->name('hangup');
        Route::get('/records',   [CallRecordController::class, 'index'])->name('records');
        Route::get('/recordings',[CallRecordController::class, 'recordings'])->name('recordings');
    });

    // ── SMS ───────────────────────────────────────────────────────────────

    Route::prefix('sms')->name('sms.')->group(function () {
        Route::get('/',                      [SmsController::class, 'index'])->name('index');
        Route::get('/{conversation}',        [SmsController::class, 'show'])->name('show');
        Route::post('/{conversation}',       [SmsController::class, 'send'])->name('send');
        Route::post('/new',                  [SmsController::class, 'compose'])->name('compose');
    });

    // ── CRM ───────────────────────────────────────────────────────────────

    Route::prefix('crm')->name('crm.')->group(function () {
        Route::resource('contacts', ContactController::class);
        Route::resource('tickets', TicketController::class);
        Route::patch('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.status');
    });

    // ── Billing ───────────────────────────────────────────────────────────

    Route::prefix('billing')->name('billing.')->middleware('nexvary.admin')->group(function () {
        Route::resource('accounts', BillingAccountController::class);
        Route::resource('rates', RateTableController::class);
        Route::get('/invoices', fn() => inertia('Billing/Invoices'))->name('invoices');
        Route::get('/reports',  fn() => inertia('Billing/Reports'))->name('reports');
    });

    // ── Server / Settings ─────────────────────────────────────────────────

    Route::prefix('settings')->name('settings.')->middleware('nexvary.admin')->group(function () {
        Route::get('/server', [ServerController::class, 'index'])->name('server');
        Route::post('/server/freeswitch/restart', [ServerController::class, 'restartFreeswitch'])->name('server.fs_restart');
        Route::get('/organization', fn() => inertia('Settings/Organization'))->name('organization');
        Route::post('/organization', [ServerController::class, 'saveOrganization'])->name('organization.save');
        Route::get('/security', fn() => inertia('Settings/Security'))->name('security');
    });

    // ── Info Pages ────────────────────────────────────────────────────────

    Route::prefix('info')->name('info.')->group(function () {
        Route::get('/about',     fn() => inertia('Info/About'))->name('about');
        Route::get('/help',      fn() => inertia('Info/Help'))->name('help');
        Route::get('/developer', fn() => inertia('Info/Developer'))->name('developer');
    });

});

// ── Webhooks (no auth, signature-verified) ────────────────────────────────
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/telnyx', [SmsController::class, 'webhook'])->name('telnyx');
});
