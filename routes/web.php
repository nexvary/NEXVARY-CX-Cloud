<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Install\WizardController;
use App\Http\Controllers\PBX\ExtensionController;
use App\Http\Controllers\PBX\TrunkController;
use App\Http\Controllers\PBX\CallRecordController;
use App\Http\Controllers\PBX\IvrController;
use App\Http\Controllers\PBX\CallQueueController;
use App\Http\Controllers\PBX\RouteController;
use App\Http\Controllers\PBX\DidController;
use App\Http\Controllers\SMS\SmsController;
use App\Http\Controllers\CRM\ContactController;
use App\Http\Controllers\CRM\TicketController;
use App\Http\Controllers\Billing\BillingAccountController;
use App\Http\Controllers\Billing\RateTableController;
use App\Http\Controllers\Server\ServerController;
use Illuminate\Support\Facades\Route;

// ── Setup Wizard (pre-install) ────────────────────────────────────────────
Route::prefix('setup')->name('install.')->group(function () {
    Route::get('/', [WizardController::class, 'index'])->name('index');
    Route::post('/step', [WizardController::class, 'step'])->name('step');
    Route::post('/test-sip', [WizardController::class, 'testSip'])->name('test_sip');
    Route::post('/complete', [WizardController::class, 'complete'])->name('complete');
    Route::get('/verify', [WizardController::class, 'verify'])->name('verify');
});

// ── Auth (accessible before install too) ─────────────────────────────────
Route::middleware(['installed'])->group(function () {
    Route::get('/login',  [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // MFA
    Route::get('/mfa',  [MfaController::class, 'show'])->name('mfa');
    Route::post('/mfa', [MfaController::class, 'verify'])->name('mfa.verify');
});

// ── Authenticated application ─────────────────────────────────────────────
Route::middleware(['installed', 'auth'])->group(function () {

    // Dashboard
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');

    // ── PBX ──────────────────────────────────────────────────────────────

    Route::prefix('pbx')->name('pbx.')->group(function () {

        // Extensions
        Route::resource('extensions', ExtensionController::class);
        Route::patch('extensions/{extension}/dnd', [ExtensionController::class, 'setDnd'])->name('extensions.dnd');
        Route::patch('extensions/{extension}/forward', [ExtensionController::class, 'setForward'])->name('extensions.forward');
        Route::get('extensions/{extension}/status', [ExtensionController::class, 'status'])->name('extensions.status');

        // Trunks
        Route::resource('trunks', TrunkController::class);
        Route::post('trunks/{trunk}/reload', [TrunkController::class, 'reload'])->name('trunks.reload');
        Route::get('trunks/{trunk}/health', [TrunkController::class, 'health'])->name('trunks.health');

        // DIDs
        Route::resource('dids', DidController::class);

        // Routes
        Route::resource('inbound-routes', RouteController::class)->parameters(['inbound-routes' => 'route'])->names(['inbound-routes']);
        Route::resource('outbound-routes', RouteController::class)->parameters(['outbound-routes' => 'route'])->names(['outbound-routes']);

        // IVR
        Route::resource('ivr', IvrController::class);

        // Queues
        Route::resource('queues', CallQueueController::class);
        Route::patch('queues/{queue}/agents/{agent}/status', [CallQueueController::class, 'agentStatus'])->name('queues.agent_status');
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

    // ── Server ────────────────────────────────────────────────────────────

    Route::prefix('settings')->name('settings.')->middleware('nexvary.admin')->group(function () {
        Route::get('/server', [ServerController::class, 'index'])->name('server');
        Route::post('/server/freeswitch/restart', [ServerController::class, 'restartFreeswitch'])->name('server.fs_restart');
        Route::get('/organization', fn() => inertia('Settings/Organization'))->name('organization');
        Route::post('/organization', [ServerController::class, 'saveOrganization'])->name('organization.save');
        Route::get('/security', fn() => inertia('Settings/Security'))->name('security');
    });
});

// ── Webhooks (no auth, signature-verified) ────────────────────────────────
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/telnyx', [SmsController::class, 'webhook'])->name('telnyx');
});
