<?php

namespace App\Http\Controllers;

use App\Models\DemoOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vmt\PayFast\Contracts\PaymentGateway;
use Vmt\PayFast\Data\CheckoutRequest;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Models\PaymentEvent;
use Vmt\PayFast\Models\PaymentTransaction;

final class DemoController
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Sign-in details did not match.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function index(Request $request)
    {
        return view('demo.index', ['orders' => DemoOrder::where('user_id', $request->user()->id)->latest()->limit(50)->get()]);
    }

    public function checkout(Request $request, PaymentGateway $gateway)
    {
        try {
            $checkout = DB::transaction(function () use ($request, $gateway) {
                $order = new DemoOrder;
                $order->forceFill(['id' => (string) Str::uuid(), 'reference' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'amount_cents' => 105, 'status' => 'PENDING', 'completion_count' => 0])->save();

                return $gateway->createCheckout(new CheckoutRequest($order->reference, $order->amount_cents, 'VMT sandbox test order', orderReference: $order->reference));
            });

            return response(view('demo.checkout', ['form' => $checkout->toHtml()]))->header('Cache-Control', 'no-store');
        } catch (PaymentException) {
            return back()->withErrors(['checkout' => 'Sandbox checkout is unavailable. Check the sandbox credentials, passphrase and HTTPS callback URLs in the demo .env.']);
        }
    }

    public function show(Request $request, string $reference)
    {
        $order = DemoOrder::where('reference', $reference)->where('user_id', $request->user()->id)->firstOrFail();
        $payment = PaymentTransaction::where('order_reference', $order->reference)->where('merchant_identity', 'default')->whereNull('tenant_id')->latest()->first();
        $events = $payment ? PaymentEvent::where('transaction_id', $payment->id)->latest('id')->limit(50)->get(['provider_status', 'outcome', 'error_category', 'received_at', 'processed_at']) : collect();

        return response(view('demo.order', compact('order', 'payment', 'events')))->header('Cache-Control', 'no-store');
    }
}
