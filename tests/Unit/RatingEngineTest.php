<?php

use App\Services\Billing\RatingEngine;

uses(Tests\TestCase::class);

it('computes billable seconds with 60s increments', function () {
    $engine = app(RatingEngine::class);

    // 65 seconds with 60s increment → rounds up to 120s
    expect($engine->computeBillableSeconds(65, 60, 0))->toBe(120);
});

it('respects minimum duration', function () {
    $engine = app(RatingEngine::class);

    // 10 seconds actual but minimum is 30 → 30s billable
    expect($engine->computeBillableSeconds(10, 6, 30))->toBe(30);
});

it('computes amount with 6 decimal precision', function () {
    $engine = app(RatingEngine::class);

    // 60s at $0.012/min = $0.012000
    $amount = $engine->computeAmount(60, '0.012000');
    expect($amount)->toBe('0.012000');
});
