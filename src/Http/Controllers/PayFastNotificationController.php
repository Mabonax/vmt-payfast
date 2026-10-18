<?php

namespace Vmt\PayFast\Http\Controllers;

use Illuminate\Http\Request;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Services\NotificationService;

final class PayFastNotificationController
{
    public function __invoke(Request $request, NotificationService $notifications)
    {
        if (strtolower(explode(';', $request->header('Content-Type', ''))[0]) !== 'application/x-www-form-urlencoded') {
            return response('Unsupported content type', 415);
        }
        try {
            // REMOTE_ADDR deliberately ignores untrusted forwarded headers.
            $notifications->process($request->getContent(), (string) $request->server('REMOTE_ADDR'));
        } catch (PaymentException) {
            return response('Invalid notification', 400);
        }

        // Infrastructure failures propagate as 5xx so PayFast can retry.
        return response('OK', 200);
    }
}
