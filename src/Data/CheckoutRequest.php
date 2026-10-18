<?php

namespace Vmt\PayFast\Data;

/** Construct only from authorized, server-side order records. */
final readonly class CheckoutRequest
{
    public function __construct(
        public string $reference,
        public int $amountCents,
        public string $itemName,
        public string $merchantContext = 'default',
        public ?string $tenantId = null,
        public ?string $orderReference = null,
        public string $currency = 'ZAR',
        public string $itemDescription = '',
        public string $firstName = '',
        public string $lastName = '',
        public string $email = '',
        public array $metadata = [],
    ) {}
}
