<?php

use App\Http\Controllers\Admin\CallFreezeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\MfaSetupController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Billing\BillingAccountController;
use App\Http\Controllers\Billing\RateTableController;
use App\Http\Controllers\CRM\ContactController;
use App\Http\Controllers\CRM\TicketController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Install\WizardController;
use App\Http\Controllers\PBX\CallQueueController;
use App\Http\Controllers\PBX\CallRecordController;
use App\Http\Controllers\PBX\DidController;
use App\Http\Controllers\PBX\ExtensionController;
use App\Http\Controllers\PBX\IvrController;
use App\Http\Controllers\PBX\RingGroupController;
use App\Http\Controllers\PBX\RouteController;
use App\Http\Controllers\PBX\TrunkController;
use App\Http\Controllers\PBX\VoicemailController;
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

    // Password reset
    Route::get('/forgot-password',         [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password',        [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}',  [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password',         [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
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
        Route::post('/select-tenant', [\App\Http\Controllers\Admin\GuideController::class, 'selectTenant'])->name('select_tenant');
        Route::post('/{module}/draft', [\App\Http\Controllers\Admin\ConfigurationController::class, 'store'])->name('draft.store');

        // Emergency Call Freeze
        Route::get('/call-freeze',                           [CallFreezeController::class, 'index'])->name('call_freeze');
        Route::post('/call-freeze/tenant',                   [CallFreezeController::class, 'activateTenant'])->name('call_freeze.tenant');
        Route::post('/call-freeze/gateway',                  [CallFreezeController::class, 'activateGateway'])->name('call_freeze.gateway');
        Route::post('/call-freeze/{freeze}/deactivate',      [CallFreezeController::class, 'deactivate'])->name('call_freeze.deactivate');
    });

    // ── PBX ───────────────────────────────────────────────────────────────

    Route::prefix('pbx')->name('pbx.')->middleware('nexvary.admin')->group(function () {
        // Extensions
        Route::resource('extensions', ExtensionController::class);
        Route::post('extensions/{extension}/enabled', [ExtensionController::class, 'setEnabled'])->name('extensions.enabled');
        Route::post('extensions/{extension}/dnd',     [ExtensionController::class, 'setDnd'])->name('extensions.dnd');
        Route::post('extensions/{extension}/forward', [ExtensionController::class, 'setForward'])->name('extensions.forward');
        Route::get('extensions/{extension}/status',   [ExtensionController::class, 'status'])->name('extensions.status');

        // SIP Trunks
        Route::resource('trunks', TrunkController::class);
        Route::post('trunks/{trunk}/reload', [TrunkController::class, 'reload'])->name('trunks.reload');
        Route::get('trunks/{trunk}/health',  [TrunkController::class, 'health'])->name('trunks.health');
        Route::get('trunks/presets/all',     [TrunkController::class, 'presets'])->name('trunks.presets');

        // DIDs
        Route::resource('dids', DidController::class);

        // Inbound & Outbound Routes
        Route::prefix('inbound-routes')->name('inbound_routes.')->group(function () {
            Route::get('/',          [RouteController::class, 'index'])->name('index');
            Route::get('/create',    [RouteController::class, 'create'])->name('create');
            Route::post('/',         [RouteController::class, 'store'])->name('store');
            Route::get('/{id}',      [RouteController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [RouteController::class, 'edit'])->name('edit');
            Route::patch('/{id}',    [RouteController::class, 'update'])->name('update');
            Route::delete('/{id}',   [RouteController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('outbound-routes')->name('outbound_routes.')->group(function () {
            Route::get('/',          [RouteController::class, 'index'])->name('index');
            Route::get('/create',    [RouteController::class, 'create'])->name('create');
            Route::post('/',         [RouteController::class, 'store'])->name('store');
            Route::get('/{id}',      [RouteController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [RouteController::class, 'edit'])->name('edit');
            Route::patch('/{id}',    [RouteController::class, 'update'])->name('update');
            Route::delete('/{id}',   [RouteController::class, 'destroy'])->name('destroy');
        });

        // IVR
        Route::resource('ivr', IvrController::class);

        // Call Queues
        Route::resource('queues', CallQueueController::class);

        // Ring Groups
        Route::resource('ring-groups', RingGroupController::class)->parameters(['ring-groups' => 'ringGroup']);

        // Voicemail
        Route::get('/voicemail',              [VoicemailController::class, 'index'])->name('voicemail.index');
        Route::get('/voicemail/{box}',        [VoicemailController::class, 'show'])->name('voicemail.show');
        Route::delete('/voicemail/{box}/{msg}', [VoicemailController::class, 'destroy'])->name('voicemail.destroy');
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
        Route::get('/security',      fn() => inertia('Settings/Security'))->name('security');
        Route::get('/users',         fn() => inertia('Settings/Users'))->name('users');
        Route::get('/integrations',  fn() => inertia('Settings/Integrations'))->name('integrations');
        Route::get('/backup',        fn() => inertia('Settings/Backup'))->name('backup');
        // Mobile device pairing — generates a one-time QR pairing token (web session auth)
        Route::post('/devices/pair', [\App\Http\Controllers\API\MobileController::class, 'generatePairingToken'])
             ->name('devices.pair');

    });

    // ── MFA Self-Service (any authenticated user, not admin-only) ─────────
    Route::prefix('settings/mfa')->name('settings.mfa.')->group(function () {
        Route::get('/enroll',               [MfaSetupController::class, 'showEnroll'])->name('enroll');
        Route::post('/enroll',              [MfaSetupController::class, 'enable'])->name('enable');
        Route::get('/recovery',             [MfaSetupController::class, 'showRecoveryCodes'])->name('recovery');
        Route::post('/recovery/regenerate', [MfaSetupController::class, 'regenerateCodes'])->name('regenerate');
        Route::post('/disable',             [MfaSetupController::class, 'disable'])->name('disable');
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
    Route::post('/freeswitch/cdr', [\App\Http\Controllers\Webhooks\FreeSwitchCdrController::class, 'store'])
        ->name('freeswitch.cdr');
});
