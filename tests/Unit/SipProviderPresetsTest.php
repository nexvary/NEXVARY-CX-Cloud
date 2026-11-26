<?php

use App\Data\SipProviderPresets;

// CX13: 25 documented provider presets + generic
$EXPECTED_DOCUMENTED = [
    'telnyx', 'twilio', 'vonage', 'plivo', 'bandwidth', 'signalwire',
    'flowroute', 'voip_ms', 'zadarma', 'didww', 'callcentric', 'anveo',
    'bulkvs', 'skyetel', 'sip_us', 'siptrunk', 'voip_innovations',
    'commio', 'voip_unlimited', 'gamma', 'sipgate', 'localphone',
    'voicetel', 'tata_communications', 'sinch',
];

it('provides 25 documented provider presets plus generic', function () use ($EXPECTED_DOCUMENTED) {
    $keys = SipProviderPresets::keys();
    // All 25 documented providers must be present
    foreach ($EXPECTED_DOCUMENTED as $key) {
        expect($keys)->toContain($key, "Missing documented preset: {$key}");
    }
    expect($keys)->toContain('generic');
    expect(count(SipProviderPresets::documented()))->toBe(25);
});

it('documented() excludes generic', function () {
    $documented = SipProviderPresets::documented();
    expect(array_keys($documented))->not->toContain('generic');
    expect(count($documented))->toBe(25);
});

it('every preset has required structural fields', function () {
    foreach (SipProviderPresets::all() as $key => $preset) {
        expect($preset)->toHaveKeys(['key', 'label', 'version', 'auth_modes', 'defaults', 'preset_fields', 'required_fields', 'instructions'])
            ->and($preset['key'])->toBe($key)
            ->and($preset['auth_modes'])->not->toBeEmpty();
    }
});

it('every preset default has at least auth_mode and sip_port', function () {
    foreach (SipProviderPresets::all() as $key => $preset) {
        foreach ($preset['defaults'] as $mode => $defaults) {
            expect($defaults)->toHaveKey('auth_mode', "Preset {$key}/{$mode} missing auth_mode")
                ->and($defaults)->toHaveKey('sip_port', "Preset {$key}/{$mode} missing sip_port");
        }
    }
});

it('keys() matches all() keys', function () {
    expect(SipProviderPresets::keys())->toBe(array_keys(SipProviderPresets::all()));
});

it('get() returns null for unknown preset', function () {
    expect(SipProviderPresets::get('unknown'))->toBeNull();
});

it('get() returns the preset for known keys', function () {
    foreach (SipProviderPresets::keys() as $key) {
        expect(SipProviderPresets::get($key))->not->toBeNull()
            ->and(SipProviderPresets::get($key)['key'])->toBe($key);
    }
});

it('SignalWire preset requires space_name in required_fields', function () {
    $sw = SipProviderPresets::get('signalwire');
    expect($sw['required_fields'])->toContain('space_name');
});

it('instructions are provided for both en and ar for all presets', function () {
    foreach (SipProviderPresets::all() as $key => $preset) {
        expect($preset['instructions'])->toHaveKey('en', "Preset {$key} missing en instructions")
            ->and($preset['instructions'])->toHaveKey('ar', "Preset {$key} missing ar instructions");
    }
});

it('Skyetel uses only ip_acl authentication', function () {
    $skyetel = SipProviderPresets::get('skyetel');
    expect($skyetel['auth_modes'])->toBe(['ip_acl']);
    expect($skyetel['defaults']['ip_acl']['register'])->toBeFalse();
});

it('no documented preset docs_url references competing PBX product names', function () {
    foreach (SipProviderPresets::documented() as $key => $preset) {
        if ($preset['docs_url'] !== null) {
            expect(strtolower($preset['docs_url']))->not->toContain('fusionpbx',
                "Preset {$key} docs_url contains competitor reference");
        }
    }
});
