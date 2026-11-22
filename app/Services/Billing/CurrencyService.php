<?php

namespace App\Services\Billing;

class CurrencyService
{
    /**
     * Format an amount string to the given decimal places for a currency.
     */
    public function format(string $amount, string $currency = 'USD', int $decimals = 6): string
    {
        return number_format((float) $amount, $decimals, '.', '');
    }

    /**
     * Convert an amount from one currency to another using a fixed rate.
     * Returns the amount unchanged when no conversion is needed.
     */
    public function convert(string $amount, string $from, string $to, string $rate = '1'): string
    {
        if ($from === $to) {
            return $amount;
        }

        return bcmul($amount, $rate, 10);
    }
}
