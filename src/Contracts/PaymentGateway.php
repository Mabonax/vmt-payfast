<?php

namespace Vmt\PayFast\Contracts;

use Vmt\PayFast\Data\CheckoutRequest;
use Vmt\PayFast\Data\CheckoutResponse;

interface PaymentGateway
{
    public function createCheckout(CheckoutRequest $request): CheckoutResponse;
}
