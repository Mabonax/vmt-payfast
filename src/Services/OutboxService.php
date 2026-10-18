<?php

namespace Vmt\PayFast\Services;

use Illuminate\Support\Facades\DB;
use Vmt\PayFast\Events\PaymentCancelled;
use Vmt\PayFast\Events\PaymentCompleted;
use Vmt\PayFast\Events\PaymentFailed;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Models\PaymentTransaction;

final class OutboxService
{
    /** At-least-once delivery: a crash after dispatch can cause a retry. Consumers must be idempotent. */
    public function dispatch(int $limit = 100): int
    {
        if (DB::transactionLevel() !== 0) {
            throw new PaymentException('Dispatch the payment outbox outside database transactions.');
        }
        $count = 0;
        $rows = DB::table('payfast_outbox')->whereNull('dispatched_at')->limit($limit)->get();
        foreach ($rows as $row) {
            $payment = PaymentTransaction::findOrFail($row->transaction_id);
            $event = match ($row->status) {
                'COMPLETED' => PaymentCompleted::class,
                'FAILED' => PaymentFailed::class,
                'CANCELLED' => PaymentCancelled::class,
            };
            event(new $event($payment->id, $payment->reference, $payment->merchant_identity, $payment->tenant_id));
            DB::table('payfast_outbox')->where('id', $row->id)->update(['dispatched_at' => now()]);
            $count++;
        }

        return $count;
    }
}
