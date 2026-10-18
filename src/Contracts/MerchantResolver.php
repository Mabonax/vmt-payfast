<?php

namespace Vmt\PayFast\Contracts;

use Vmt\PayFast\Data\Merchant;

interface MerchantResolver
{
    public function resolve(string $context, ?string $tenantId): Merchant;
}
