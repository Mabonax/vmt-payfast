<?php

namespace Vmt\PayFast\Http\Controllers;

final class PayFastReturnController
{
    public function __invoke()
    {
        return response('Payment verification may still be pending. View your order in your account for its current status.')->header('Cache-Control', 'no-store');
    }
}
