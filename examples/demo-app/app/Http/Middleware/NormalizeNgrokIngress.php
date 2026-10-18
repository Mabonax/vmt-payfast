<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Specific to a directly connected loopback ngrok agent, never a generic proxy-chain parser. */
final class NormalizeNgrokIngress
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('demo.ngrok_ingress', false)) {
            return $next($request);
        }

        $sourceHeaders = $request->headers->all('x-forwarded-for');
        $protoHeaders = $request->headers->all('x-forwarded-proto');
        $request->headers->remove('X-PayFast-Client-IP');
        $request->headers->remove('X-Forwarded-Proto');
        $source = $this->lastValue($sourceHeaders);
        $proto = $this->lastValue($protoHeaders);
        $expectedHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $ngrokHost = is_string($expectedHost) && preg_match('/\.(ngrok-free\.(app|dev)|ngrok\.(app|dev|io))$/D', $expectedHost);
        if ($request->server('REMOTE_ADDR') === '127.0.0.1'
            && $ngrokHost && $request->getHost() === $expectedHost
            && $proto === 'https' && filter_var($source, FILTER_VALIDATE_IP) !== false) {
            // ngrok documents that it APPENDS the actual client IP/scheme. Ignore caller prefixes.
            $request->headers->set('X-PayFast-Client-IP', $source);
            $request->headers->set('X-Forwarded-Proto', 'https');
        }

        return $next($request);
    }

    private function lastValue(array $headers): string
    {
        $combined = implode(',', $headers);
        if (strlen($combined) > 4096) {
            return '';
        }
        $parts = explode(',', $combined);

        return trim($parts[array_key_last($parts)]);
    }
}
