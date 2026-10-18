<?php

namespace Vmt\PayFast\Services;

use Vmt\PayFast\Contracts\ProviderSource;

final class DnsProviderSource implements ProviderSource
{
    public function accepts(string $ip): bool
    {
        foreach (['www.payfast.co.za', 'sandbox.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'] as $host) {
            if (in_array($ip, gethostbynamel($host) ?: [], true)) {
                return true;
            }
        }

        return false;
    }
}
