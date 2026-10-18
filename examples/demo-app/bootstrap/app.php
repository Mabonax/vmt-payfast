<?php

use App\Http\Middleware\NormalizeNgrokIngress;
use App\Http\Middleware\SandboxOnly;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(NormalizeNgrokIngress::class);
        $middleware->append(SandboxOnly::class);
        $middleware->trustProxies(
            at: ['127.0.0.1'],
            headers: Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'merchant_key', 'passphrase', 'signature']);
        // Do not log exception contexts: HTTP errors may carry request bodies or credentials.
        $exceptions->report(function (Throwable $exception) {
            logger()->error('Demo operation failed', ['category' => class_basename($exception)]);

            return false;
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
                return null;
            }
            if ($exception instanceof ValidationException || $exception instanceof AuthenticationException) {
                return null;
            }

            return response('Sandbox demo operation unavailable. Inspect the redacted server diagnostics.', 503)->header('Cache-Control', 'no-store');
        });
    })->create();
