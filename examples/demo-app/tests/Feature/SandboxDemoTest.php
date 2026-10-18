<?php

namespace Tests\Feature;

use App\Http\Middleware\NormalizeNgrokIngress;
use App\Models\DemoOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Vmt\PayFast\Contracts\PaymentGateway;
use Vmt\PayFast\Contracts\ProviderSource;
use Vmt\PayFast\Data\CheckoutRequest;
use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Events\PaymentCompleted;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Models\PaymentTransaction;
use Vmt\PayFast\Services\NotificationService;
use Vmt\PayFast\Services\SignatureService;

final class SandboxDemoTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.ngrok_ingress' => false]);
        config(['payfast.mode' => 'sandbox', 'payfast.merchant_id' => '12345678', 'payfast.merchant_key' => 'test-key', 'payfast.passphrase' => 'unit-test-passphrase', 'payfast.return_url' => 'https://example.test/payments/payfast/return', 'payfast.cancel_url' => 'https://example.test/payments/payfast/cancel', 'payfast.notify_url' => 'https://example.test/payments/payfast/notify']);
        Http::preventStrayRequests();
    }

    private function checkout(): PaymentTransaction
    {
        $this->actingAs(User::factory()->create());
        $this->post('/checkout', ['amount_cents' => 1])->assertOk()->assertSee('sandbox.payfast.co.za/eng/process');

        return PaymentTransaction::firstOrFail();
    }

    public function test_authentication_and_server_amount_and_scoped_audit(): void
    {
        $this->post('/checkout')->assertRedirect('/login');
        $payment = $this->checkout();
        $order = DemoOrder::firstOrFail();
        $this->assertSame(500, $payment->amount_cents);
        $this->assertSame(500, $order->amount_cents);
        $this->get('/orders/'.$order->reference)->assertSee('PENDING')->assertDontSee('unit-test-passphrase')->assertDontSee('credential_snapshot');
        $this->actingAs(User::factory()->create());
        $this->get('/orders/'.$order->reference)->assertNotFound();
    }

    public function test_missing_credentials_roll_back_order_and_redact_error(): void
    {
        $this->actingAs(User::factory()->create());
        config(['payfast.merchant_key' => '']);
        $this->post('/checkout')->assertSessionHasErrors('checkout');
        $this->assertSame(0, DemoOrder::count());
        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_production_is_blocked_at_http_and_gateway_boundaries(): void
    {
        config(['payfast.mode' => 'production']);
        $this->get('/login')->assertStatus(503);
        $this->expectException(PaymentException::class);
        app(PaymentGateway::class)->createCheckout(new CheckoutRequest('order', 105, 'Item'));
    }

    public function test_verified_payment_queues_and_fulfils_once_after_retry(): void
    {
        config(['queue.default' => 'database']);
        $payment = $this->checkout();
        $this->app->bind(ProviderSource::class, fn () => new class implements ProviderSource
        {
            public function accepts(string $ip): bool
            {
                return $ip === '192.0.2.1';
            }
        });
        Http::fake(['*/eng/query/validate' => Http::response('VALID')]);
        $fields = ['m_payment_id' => $payment->reference, 'pf_payment_id' => '777777', 'payment_status' => 'COMPLETE', 'amount_gross' => '5.00', 'merchant_id' => '12345678'];
        $fields['signature'] = app(SignatureService::class)->notification($fields, 'unit-test-passphrase');
        app(NotificationService::class)->process(http_build_query($fields), '192.0.2.1');
        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame(0, DemoOrder::firstOrFail()->completion_count);
        $this->artisan('payfast:dispatch-outbox')->assertSuccessful();
        $this->assertSame(1, DB::table('jobs')->count());
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1])->assertSuccessful();
        // Simulate an at-least-once outbox replay / durable queue retry.
        event(new PaymentCompleted($payment->id, $payment->reference, 'default', null));
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1])->assertSuccessful();
        $this->assertSame(1, DemoOrder::firstOrFail()->completion_count);
        $this->assertSame(1, DB::table('payfast_fulfilments')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_return_and_cancel_never_complete_order(): void
    {
        $payment = $this->checkout();
        $this->get('/payments/payfast/return?payfast_reference='.$payment->reference)->assertOk()->assertSee('PENDING');
        $this->get('/payments/payfast/cancel?payfast_reference='.$payment->reference)->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(0, DemoOrder::firstOrFail()->completion_count);
    }

    public function test_https_header_is_trusted_only_from_local_ingress(): void
    {
        $this->app['router']->get('/test-ingress-scheme', fn (Request $request) => response()->json(['secure' => $request->isSecure()]));
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://example.test/test-ingress-scheme', ['X-Forwarded-Proto' => 'https'])->assertJson(['secure' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get('http://example.test/test-ingress-scheme', ['X-Forwarded-Proto' => 'https'])->assertJson(['secure' => false]);
    }

    public function test_ngrok_uses_appended_source_and_removes_forged_prefixes(): void
    {
        config(['demo.ngrok_ingress' => true, 'app.url' => 'https://acceptance.ngrok-free.app']);
        $middleware = new NormalizeNgrokIngress;
        $request = Request::create('http://acceptance.ngrok-free.app/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('X-PayFast-Client-IP', '192.0.2.1');
        $request->headers->set('X-Forwarded-For', '192.0.2.1, 198.51.100.10');
        $request->headers->set('X-Forwarded-Proto', 'http, https');
        $middleware->handle($request, function ($normalized) {
            $this->assertSame('198.51.100.10', $normalized->header('X-PayFast-Client-IP'));
            $this->assertSame('https', $normalized->header('X-Forwarded-Proto'));
        });
    }

    public function test_ngrok_normalization_fails_closed_for_untrusted_or_malformed_requests(): void
    {
        config(['demo.ngrok_ingress' => true, 'app.url' => 'https://acceptance.ngrok-free.app']);
        foreach ([
            ['192.0.2.2', 'acceptance.ngrok-free.app', '198.51.100.10', 'https'],
            ['127.0.0.1', 'wrong.ngrok-free.app', '198.51.100.10', 'https'],
            ['127.0.0.1', 'acceptance.ngrok-free.app', '', 'https'],
            ['127.0.0.1', 'acceptance.ngrok-free.app', '198.51.100.10, invalid', 'https'],
            ['127.0.0.1', 'acceptance.ngrok-free.app', '198.51.100.10', 'https, http'],
        ] as [$peer, $host, $source, $proto]) {
            $request = Request::create('http://'.$host.'/');
            $request->server->set('REMOTE_ADDR', $peer);
            $request->headers->set('X-PayFast-Client-IP', '192.0.2.1');
            $request->headers->set('X-Forwarded-For', $source);
            $request->headers->set('X-Forwarded-Proto', $proto);
            (new NormalizeNgrokIngress)->handle($request, function ($normalized) {
                $this->assertNull($normalized->header('X-PayFast-Client-IP'));
                $this->assertNull($normalized->header('X-Forwarded-Proto'));
            });
        }
    }
}
