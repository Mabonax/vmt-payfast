<?php

// Include from the host's routes/web.php ONLY for local sandbox demonstration.
use Illuminate\Support\Facades\Route;
use Vmt\PayFast\Contracts\PaymentGateway;
use Vmt\PayFast\Data\CheckoutRequest;

if (app()->environment('local') && config('payfast.mode') === 'sandbox') {
    Route::middleware('auth')->group(function () {
        Route::get('/demo/payfast', fn () => response('<form method="post" action="/demo/payfast">'.csrf_field().'<button>Pay sandbox test order R1.05</button></form>'));
        Route::post('/demo/payfast', function (PaymentGateway $gateway) {
            // Fixed server-side demo amount. Real applications load and authorize their order.
            $checkout = $gateway->createCheckout(new CheckoutRequest('sandbox-demo', 105, 'Sandbox test order'));

            return response($checkout->toHtml())->header('Cache-Control', 'no-store');
        });
    });
}
