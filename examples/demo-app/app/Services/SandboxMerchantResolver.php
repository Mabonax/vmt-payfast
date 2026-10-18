<?php

namespace App\Services;

use Vmt\PayFast\Contracts\MerchantResolver;
use Vmt\PayFast\Data\Merchant;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Services\ConfigMerchantResolver;

final class SandboxMerchantResolver implements MerchantResolver
{
    public function resolve(string $context, ?string $tenantId): Merchant
    {
        if (config('payfast.mode') !== 'sandbox' || app()->environment('production')) {
            throw new PaymentException('Demo cannot create production payments.');
        }

        return (new ConfigMerchantResolver)->resolve($context, $tenantId);
    }
}
