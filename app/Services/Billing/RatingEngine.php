<?php

namespace App\Services\Billing;

use App\Models\CallRecord;
use App\Models\RateTable;
use App\Models\BillingAccount;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Telecom call rating engine.
 * Rates completed calls against configured rate tables.
 * Never modifies call records — only produces charge records.
 */
class RatingEngine
{
    public function __construct(
        private readonly CurrencyService $currency,
    ) {}

    /**
     * Rate a single finalized call record against an account's rate table.
     * Returns a charge record (not persisted — caller decides).
     */
    public function rateCall(CallRecord $call, BillingAccount $account): array
    {
        if (!$call->billable) {
            return ['billable' => false, 'amount' => '0.000000', 'currency' => $account->currency];
        }

        if ($call->duration_seconds < 1) {
            return ['billable' => false, 'amount' => '0.000000', 'currency' => $account->currency, 'reason' => 'zero_duration'];
        }

        $rateTable = $account->rateTable;
        if (!$rateTable) {
            throw new RuntimeException("No rate table assigned to account {$account->id}");
        }

        $rate = $this->findRate($rateTable, $call->destination_number);
        if (!$rate) {
            return [
                'billable' => false,
                'amount'   => '0.000000',
                'currency' => $account->currency,
                'reason'   => 'no_matching_rate',
            ];
        }

        $billableSeconds = $this->computeBillableSeconds(
            $call->duration_seconds,
            $rate['billing_increment'],
            $rate['minimum_duration']
        );

        $amount = $this->computeAmount(
            $billableSeconds,
            $rate['rate_per_minute'],
            $rate['connection_fee']
        );

        return [
            'billable'          => true,
            'call_record_id'    => $call->id,
            'billing_account_id'=> $account->id,
            'rate_table_id'     => $rateTable->id,
            'rate_id'           => $rate['id'],
            'destination'       => $call->destination_number,
            'prefix_matched'    => $rate['prefix'],
            'duration_seconds'  => $call->duration_seconds,
            'billable_seconds'  => $billableSeconds,
            'rate_per_minute'   => $rate['rate_per_minute'],
            'connection_fee'    => $rate['connection_fee'],
            'amount'            => $amount,
            'currency'          => $account->currency,
            'rated_at'          => now()->toDateTimeString(),
        ];
    }

    /**
     * Rate and persist all unrated calls for an account.
     * Returns count of calls rated.
     */
    public function rateUnratedCalls(BillingAccount $account): int
    {
        $calls = CallRecord::where('billing_account_id', $account->id)
            ->where('rated', false)
            ->where('billable', true)
            ->get();

        $count = 0;
        DB::transaction(function () use ($calls, $account, &$count) {
            foreach ($calls as $call) {
                $charge = $this->rateCall($call, $account);

                if ($charge['billable']) {
                    DB::table('call_charges')->insert(array_merge($charge, [
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));

                    $call->update(['rated' => true, 'rated_at' => now()]);
                    $count++;
                }
            }
        });

        return $count;
    }

    // ── Rate Lookup ───────────────────────────────────────────────────────────

    private function findRate(RateTable $table, string $number): ?array
    {
        $number = preg_replace('/[^0-9+]/', '', $number);
        if (str_starts_with($number, '+')) {
            $number = ltrim($number, '+');
        }

        // Find longest matching prefix
        $rate = DB::table('rates')
            ->where('rate_table_id', $table->id)
            ->where('enabled', true)
            ->whereRaw("? LIKE CONCAT(prefix, '%')", [$number])
            ->orderByRaw('LENGTH(prefix) DESC')
            ->first();

        return $rate ? (array) $rate : null;
    }

    // ── Billing Math ──────────────────────────────────────────────────────────

    private function computeBillableSeconds(
        int $actualSeconds,
        int $increment,
        int $minimum
    ): int {
        if ($actualSeconds < $minimum) {
            return $minimum;
        }

        if ($increment <= 0) {
            return $actualSeconds;
        }

        // Round up to nearest billing increment
        return (int) ceil($actualSeconds / $increment) * $increment;
    }

    private function computeAmount(
        int $billableSeconds,
        string $ratePerMinute,
        string $connectionFee
    ): string {
        $minutes = bcdiv((string) $billableSeconds, '60', 10);
        $callCost = bcmul($minutes, $ratePerMinute, 10);
        $total = bcadd($callCost, $connectionFee, 10);

        // Round to 6 decimal places (standard telecom billing precision)
        return number_format((float) $total, 6, '.', '');
    }

    // ── Balance Management ────────────────────────────────────────────────────

    public function deductBalance(BillingAccount $account, string $amount): bool
    {
        if ($account->type !== 'prepaid') {
            return true; // Postpaid: always allow
        }

        return DB::transaction(function () use ($account, $amount) {
            $fresh = BillingAccount::lockForUpdate()->find($account->id);

            if (bccomp($fresh->balance, $amount, 6) < 0) {
                return false; // Insufficient balance
            }

            $fresh->update([
                'balance' => bcsub($fresh->balance, $amount, 6),
            ]);

            return true;
        });
    }

    public function checkSpendingLimit(BillingAccount $account, string $estimatedCost): bool
    {
        if (!$account->daily_limit) {
            return true;
        }

        $todaySpend = DB::table('call_charges')
            ->where('billing_account_id', $account->id)
            ->whereDate('rated_at', today())
            ->sum(DB::raw('CAST(amount AS DECIMAL(20,6))'));

        $projected = bcadd((string) $todaySpend, $estimatedCost, 6);
        return bccomp($projected, $account->daily_limit, 6) <= 0;
    }
}
