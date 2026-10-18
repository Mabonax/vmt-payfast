<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class SandboxOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('payfast.mode') === 'sandbox' && ! app()->environment('production'), 503, 'Sandbox demo cannot process production payments.');

        return $next($request);
    }
}
