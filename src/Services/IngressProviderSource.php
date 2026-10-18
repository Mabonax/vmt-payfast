<?php

namespace Vmt\PayFast\Services;

use Illuminate\Http\Request;
use Vmt\PayFast\Contracts\ProviderSource;

/** Use only when an approved ingress overwrites this header and direct upstream access is blocked. */
final class IngressProviderSource implements ProviderSource
{
    public function __construct(private Request $request, private ProviderSource $provider, private array $trustedPeers) {}

    public function accepts(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP) || ! in_array($ip, $this->trustedPeers, true)) {
            return false;
        }
        $source = $this->request->header('X-PayFast-Client-IP');

        // No lists, ports, Forwarded syntax, arbitrary fallbacks or client-controlled proxy chains.
        return is_string($source) && filter_var($source, FILTER_VALIDATE_IP) !== false && $this->provider->accepts($source);
    }
}
