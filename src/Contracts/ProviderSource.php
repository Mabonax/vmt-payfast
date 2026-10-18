<?php

namespace Vmt\PayFast\Contracts;

interface ProviderSource
{
    public function accepts(string $ip): bool;
}
