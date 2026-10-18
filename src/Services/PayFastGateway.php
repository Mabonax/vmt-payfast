<?php

namespace Vmt\PayFast\Services;

use Vmt\PayFast\Contracts\PaymentGateway;
use Vmt\PayFast\Data\CheckoutRequest;
use Vmt\PayFast\Data\CheckoutResponse;

final class PayFastGateway implements PaymentGateway
{
    public function __construct(private CheckoutService $checkout) {}

    public function createCheckout(CheckoutRequest $request): CheckoutResponse
    {
        return $this->checkout->create($request);
    }
}
