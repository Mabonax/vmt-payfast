<?php

namespace Vmt\PayFast\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Vmt\PayFast\Models\PaymentTransaction;

final class PayFastReturnController
{
    public function __invoke(Request $request)
    {
        $reference = $request->query('payfast_reference');
        if ($request->user() && is_string($reference)) {
            $payment = PaymentTransaction::where('reference', $reference)->first();
            if ($payment && Gate::forUser($request->user())->allows('view-payfast-payment', $payment)) {
                return response('Current payment status: '.$payment->status->value.($payment->status->value === 'PENDING' ? '. Awaiting verified provider notification.' : '.'))->header('Cache-Control', 'no-store');
            }
        }

        return response('Payment verification may still be pending. View your order in your account for its current status.')->header('Cache-Control', 'no-store');
    }
}
