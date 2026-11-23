<?php

use App\Data\SipProviderPresets;

it('provides all five built-in presets', function () {
    $presets = SipProviderPresets::all();
    expect(array_keys($presets))->toBe(['telnyx', 'twilio', 'signalwire', 'voicetel', 'generic']);
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
            expect($defaults)->toHaveKey('auth_mode')
                ->and($defaults)->toHaveKey('sip_port');
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
        expect($preset['instructions'])->toHaveKey('en')
            ->and($preset['instructions'])->toHaveKey('ar');
    }
});
