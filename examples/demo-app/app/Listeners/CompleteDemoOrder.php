<?php

namespace App\Listeners;

use App\Models\DemoOrder;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Events\PaymentCompleted;
use Vmt\PayFast\Services\FulfilmentService;

final class CompleteDemoOrder implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public int $timeout = 30;

    public int $backoff = 10;

    public function handle(PaymentCompleted $event): void
    {
        app(FulfilmentService::class)->once($event->transactionId, $event->merchantIdentity, $event->tenantId, 'demo-order-v1', function ($payment) {
            if ($payment->mode !== 'sandbox') {
                throw new \RuntimeException('Production payment rejected by sandbox demo.');
            }
            $order = DemoOrder::where('reference', $payment->order_reference)->lockForUpdate()->firstOrFail();
            if ($order->amount_cents !== $payment->amount_cents) {
                throw new \RuntimeException('Order amount mismatch.');
            }
            if ($order->status !== PaymentStatus::Completed->value) {
                $order->forceFill(['status' => PaymentStatus::Completed->value, 'completion_count' => $order->completion_count + 1])->save();
            }
        });
    }
}
