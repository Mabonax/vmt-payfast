<?php

namespace Vmt\PayFast\Data;

final readonly class PaymentResult
{
    public function __construct(public string $reference, public string $outcome) {}
}
