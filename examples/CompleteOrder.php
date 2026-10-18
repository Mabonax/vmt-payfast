<?php

namespace App\Listeners;

use App\Contracts\OrderRepository;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Vmt\PayFast\Events\PaymentCompleted;
use Vmt\PayFast\Services\FulfilmentService;

// Adapt OrderRepository and OrderService to your application's real contracts.
final class CompleteOrder implements ShouldQueue
{
    public function __construct(
        private FulfilmentService $fulfilment,
        private OrderRepository $orders,
        private OrderService $orderService,
    ) {}

    public function handle(PaymentCompleted $event): void
    {
        $this->fulfilment->once($event->transactionId, $event->merchantIdentity, $event->tenantId, 'complete-order-v1', function ($payment) use ($event) {
            // Same DB connection; repository must scope context and lock the order.
            $order = $this->orders->lockByReference($payment->order_reference, $event->merchantIdentity, $event->tenantId);
            // Host service must also guard multiple paid attempts for the same order.
            $this->orderService->completePaidOrderOnce($order, $payment->reference, $payment->amount_cents);
        });
    }
}
