<?php

namespace Vmt\PayFast\Http\Controllers;

final class PayFastCancelController
{
    public function __invoke()
    {
        return response('You left checkout. This does not establish payment failure or cancellation. View your order in your account.')->header('Cache-Control', 'no-store');
    }
}
