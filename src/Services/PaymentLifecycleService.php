<?php

namespace Vmt\PayFast\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Models\PaymentEvent;
use Vmt\PayFast\Models\PaymentTransaction;

final class PaymentLifecycleService
{
    /** Trusted server-side administrative decision only; never wire this to a browser redirect. */
    public function close(string $id, string $merchant, ?string $tenantId, PaymentStatus $status): void
    {
        if (! in_array($status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
            throw new PaymentException('Only verified ITN processing can complete a payment.');
        }
        DB::transaction(function () use ($id, $merchant, $tenantId, $status) {
            $payment = PaymentTransaction::whereKey($id)->where('merchant_identity', $merchant)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
            if (! $payment->status->allows($status)) {
                throw new PaymentException('Invalid payment transition.');
            }
            $payment->status = $status;
            $payment->save();
            DB::table('payfast_outbox')->insert(['id' => (string) Str::uuid(), 'transaction_id' => $id, 'status' => $status->value]);
            (new PaymentEvent)->forceFill(['transaction_id' => $id, 'provider_status' => null, 'outcome' => 'administratively_closed', 'received_at' => now(), 'processed_at' => now()])->save();
        }, 3);
    }
}
