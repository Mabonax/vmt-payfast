<?php

namespace Vmt\PayFast\Services;

use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Models\PaymentTransaction;

final class ReconciliationService
{
    /** An exception report, not a provider REST status lookup or settlement reconciliation. */
    public function pending(string $merchant, ?string $tenantId, \DateTimeInterface $before): array
    {
        return PaymentTransaction::query()->where('merchant_identity', $merchant)->where('tenant_id', $tenantId)
            ->where('status', PaymentStatus::Pending->value)->where('created_at', '<', $before)
            ->get(['reference', 'application_reference', 'amount_cents', 'created_at'])->toArray();
    }
}
