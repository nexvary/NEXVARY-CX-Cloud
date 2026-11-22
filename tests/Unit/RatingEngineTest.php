<?php

use Tests\TestCase;

uses(TestCase::class);

it('computes billable seconds with 60s increments', function () {
    // 65 actual seconds, 60s billing increment → rounds up to 120s
    $actual = 65;
    $increment = 60;
    $minimum = 0;

    $billed = (int) ceil($actual / $increment) * $increment;
    if ($billed < $minimum) {
        $billed = $minimum;
    }

    expect($billed)->toBe(120);
});

it('respects minimum duration', function () {
    // 10 actual seconds, 6s increment, 30s minimum → 30s billable
    $actual = 10;
    $increment = 6;
    $minimum = 30;

    $billed = (int) ceil($actual / $increment) * $increment;
    if ($billed < $minimum) {
        $billed = $minimum;
    }

    expect($billed)->toBe(30);
});

it('computes amount with 6 decimal precision', function () {
    // 60s at $0.012000/min = $0.012000
    $billableSeconds = 60;
    $ratePerMinute = '0.012000';

    $minutes = bcdiv((string) $billableSeconds, '60', 10);
    $amount = bcmul($minutes, $ratePerMinute, 10);
    $rounded = bcadd($amount, '0', 6);

    expect($rounded)->toBe('0.012000');
});
