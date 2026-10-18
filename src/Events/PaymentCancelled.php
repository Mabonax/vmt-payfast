<?php

namespace Vmt\PayFast\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class PaymentCancelled implements ShouldDispatchAfterCommit
{
    public function __construct(public string $transactionId, public string $reference, public string $merchantIdentity, public ?string $tenantId) {}
}
