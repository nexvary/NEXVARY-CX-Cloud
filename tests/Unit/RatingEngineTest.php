<?php

use App\Services\Billing\RatingEngine;
use App\Services\Billing\CurrencyService;
use Tests\TestCase;

uses(TestCase::class);

function makeEngine(): RatingEngine
{
    return new RatingEngine(new CurrencyService());
}

function callPrivate(object $obj, string $method, array $args): mixed
{
    $ref = new \ReflectionMethod($obj, $method);
    return $ref->invoke($obj, ...$args);
}

it('computes billable seconds with 60s increments', function () {
    $engine = makeEngine();
    // 65 actual seconds, 60s billing increment → rounds up to 120s
    $result = callPrivate($engine, 'computeBillableSeconds', [65, 60, 0]);
    expect($result)->toBe(120);
});

it('respects minimum duration', function () {
    $engine = makeEngine();
    // 10 actual seconds, 6s increment → 12s billed, but minimum is 30s
    $result = callPrivate($engine, 'computeBillableSeconds', [10, 6, 30]);
    expect($result)->toBe(30);
});

it('computes amount with 6 decimal precision', function () {
    $engine = makeEngine();
    // 60s at $0.012000/min + $0.00 connection fee = $0.012000
    $result = callPrivate($engine, 'computeAmount', [60, '0.012000', '0.000000']);
    expect($result)->toBe('0.012000');
});

it('zero increment returns actual seconds unchanged', function () {
    $engine = makeEngine();
    $result = callPrivate($engine, 'computeBillableSeconds', [73, 0, 0]);
    expect($result)->toBe(73);
});

it('connection fee is added to call cost', function () {
    $engine = makeEngine();
    // 60s at $0.010000/min + $0.005000 connection fee = $0.015000
    $result = callPrivate($engine, 'computeAmount', [60, '0.010000', '0.005000']);
    expect($result)->toBe('0.015000');
});
