<?php

namespace Vmt\PayFast\Services;

use Illuminate\Support\Facades\DB;
use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Models\PaymentTransaction;

final class FulfilmentService
{
    /** Callback must only mutate the SAME database connection. External effects need their own outbox. */
    public function once(string $transactionId, string $merchant, ?string $tenantId, string $consumer, callable $work): bool
    {
        return DB::transaction(function () use ($transactionId, $merchant, $tenantId, $consumer, $work) {
            $payment = PaymentTransaction::whereKey($transactionId)->where('merchant_identity', $merchant)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
            if ($payment->status !== PaymentStatus::Completed) {
                throw new PaymentException('Cannot fulfil an unverified payment.');
            }
            if (DB::table('payfast_fulfilments')->where('transaction_id', $payment->id)->where('consumer', $consumer)->exists()) {
                return false;
            }
            $work($payment);
            DB::table('payfast_fulfilments')->insert(['transaction_id' => $payment->id, 'consumer' => $consumer, 'fulfilled_at' => now()]);

            return true;
        }, 3);
    }
}
