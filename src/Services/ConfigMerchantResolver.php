<?php

namespace Vmt\PayFast\Services;

use Vmt\PayFast\Contracts\MerchantResolver;
use Vmt\PayFast\Data\Merchant;
use Vmt\PayFast\Exceptions\PaymentException;

final class ConfigMerchantResolver implements MerchantResolver
{
    public function resolve(string $context, ?string $tenantId): Merchant
    {
        if ($context !== 'default' || $tenantId !== null) {
            throw new PaymentException('Bind a tenant-aware MerchantResolver for this context.');
        }

        return new Merchant('default', null, (string) config('payfast.mode'), (string) config('payfast.merchant_id'), (string) config('payfast.merchant_key'), (string) config('payfast.passphrase'), (string) config('payfast.return_url'), (string) config('payfast.cancel_url'), (string) config('payfast.notify_url'));
    }
}
