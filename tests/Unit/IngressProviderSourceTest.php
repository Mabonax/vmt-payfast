<?php

namespace Vmt\PayFast\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Vmt\PayFast\Contracts\ProviderSource;
use Vmt\PayFast\Services\IngressProviderSource;

final class IngressProviderSourceTest extends TestCase
{
    public function test_requires_trusted_peer_single_overwritten_ip_and_provider_match(): void
    {
        $provider = new class implements ProviderSource
        {
            public function accepts(string $ip): bool
            {
                return $ip === '192.0.2.10';
            }
        };
        foreach ([
            ['10.0.0.2', '192.0.2.10', true],
            ['10.0.0.3', '192.0.2.10', false],
            ['10.0.0.2', null, false],
            ['10.0.0.2', '192.0.2.10, 192.0.2.20', false],
            ['10.0.0.2', '192.0.2.10:443', false],
            ['10.0.0.2', '192.0.2.20', false],
            ['10.0.0.2', 'invalid', false],
        ] as [$peer, $header, $expected]) {
            $request = Request::create('/payments/payfast/notify', 'POST');
            if ($header !== null) {
                $request->headers->set('X-PayFast-Client-IP', $header);
            }
            $request->headers->set('X-Forwarded-For', '192.0.2.10');
            $request->headers->set('Referer', 'https://www.payfast.co.za/');
            $this->assertSame($expected, (new IngressProviderSource($request, $provider, ['10.0.0.2']))->accepts($peer));
        }
    }

    public function test_empty_trust_list_fails_closed(): void
    {
        $provider = $this->createMock(ProviderSource::class);
        $provider->expects($this->never())->method('accepts');
        $this->assertFalse((new IngressProviderSource(Request::create('/'), $provider, []))->accepts('127.0.0.1'));
    }
}
