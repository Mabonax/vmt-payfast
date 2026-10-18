<?php

namespace Vmt\PayFast\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Vmt\PayFast\Contracts\MerchantResolver;
use Vmt\PayFast\Contracts\PaymentGateway;
use Vmt\PayFast\Contracts\ProviderSource;
use Vmt\PayFast\Data\CheckoutRequest;
use Vmt\PayFast\Data\Merchant;
use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Events\PaymentCancelled;
use Vmt\PayFast\Events\PaymentCompleted;
use Vmt\PayFast\Events\PaymentFailed;
use Vmt\PayFast\Exceptions\PaymentException;
use Vmt\PayFast\Models\PaymentTransaction;
use Vmt\PayFast\Services\FulfilmentService;
use Vmt\PayFast\Services\NotificationService;
use Vmt\PayFast\Services\OutboxService;
use Vmt\PayFast\Services\PaymentLifecycleService;
use Vmt\PayFast\Services\ReconciliationService;
use Vmt\PayFast\Services\SignatureService;
use Vmt\PayFast\Tests\TestCase;

final class PaymentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(ProviderSource::class, fn () => new class implements ProviderSource
        {
            public function accepts(string $ip): bool
            {
                return $ip === '192.0.2.1';
            }
        });
        Http::preventStrayRequests();
        Http::fake(['*/eng/query/validate' => Http::response('VALID')]);
    }

    private function checkout(?string $tenant = null): PaymentTransaction
    {
        $checkout = app(PaymentGateway::class)->createCheckout(new CheckoutRequest('order-1', 105, 'Item <test>', tenantId: $tenant));
        $this->assertSame('1.05', $checkout->fields['amount']);
        $this->assertStringNotContainsString('passphrase', $checkout->toHtml());
        $this->assertStringContainsString('&lt;test&gt;', $checkout->toHtml());

        return PaymentTransaction::where('reference', $checkout->reference)->firstOrFail();
    }

    private function body(PaymentTransaction $payment, array $overrides = [], bool $resign = true): string
    {
        $fields = ['m_payment_id' => $payment->reference, 'pf_payment_id' => '987654321', 'payment_status' => 'COMPLETE', 'item_name' => 'Item', 'amount_gross' => '1.05', 'merchant_id' => '12345678'];
        $original = app(SignatureService::class)->notification($fields, 'test passphrase');
        $fields = array_replace($fields, $overrides);
        $fields['signature'] = $resign ? app(SignatureService::class)->notification($fields, 'test passphrase') : $original;

        return http_build_query($fields, '', '&', PHP_QUERY_RFC1738);
    }

    public function test_checkout_endpoint_signature_and_encrypted_snapshot(): void
    {
        $payment = $this->checkout();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $raw = DB::table('payfast_transactions')->value('credential_snapshot');
        $this->assertStringNotContainsString('test passphrase', $raw);
        $this->assertArrayNotHasKey('credential_snapshot', $payment->toArray());
        $response = app(PaymentGateway::class)->createCheckout(new CheckoutRequest('order-2', 100, 'Item'));
        $this->assertSame('https://sandbox.payfast.co.za/eng/process', $response->url);
        $fields = $response->fields;
        unset($fields['signature']);
        $this->assertSame(app(SignatureService::class)->checkout($fields, 'test passphrase'), $response->fields['signature']);
    }

    public function test_production_endpoint(): void
    {
        config(['payfast.mode' => 'production']);
        $response = app(PaymentGateway::class)->createCheckout(new CheckoutRequest('order', 500, 'Item'));
        $this->assertSame('https://www.payfast.co.za/eng/process', $response->url);
    }

    public function test_production_minimum_rejects_before_persistence_and_accepts_boundary(): void
    {
        config(['payfast.mode' => 'production']);
        foreach ([1, 499] as $amount) {
            try {
                app(PaymentGateway::class)->createCheckout(new CheckoutRequest('below-minimum', $amount, 'Item'));
                $this->fail('Expected minimum amount rejection.');
            } catch (PaymentException $exception) {
                $this->assertStringContainsString('ZAR 5.00', $exception->getMessage());
                $this->assertSame(0, PaymentTransaction::count());
            }
        }
        $checkout = app(PaymentGateway::class)->createCheckout(new CheckoutRequest('boundary', 500, 'Item'));
        $this->assertSame('5.00', $checkout->fields['amount']);
        $this->assertSame(1, PaymentTransaction::count());
    }

    public function test_invalid_amount_and_missing_credentials_leave_no_records(): void
    {
        foreach ([0, -1, 10000000000] as $amount) {
            try {
                app(PaymentGateway::class)->createCheckout(new CheckoutRequest('order', $amount, 'Item'));
                $this->fail('Expected rejection');
            } catch (PaymentException) {
                $this->assertSame(0, PaymentTransaction::count());
            }
        }
        config(['payfast.merchant_key' => '']);
        $this->expectException(PaymentException::class);
        $this->checkout();
    }

    public function test_known_sandbox_credentials_rejected_in_production(): void
    {
        config(['payfast.mode' => 'production', 'payfast.merchant_id' => '10000100']);
        $this->expectException(PaymentException::class);
        $this->checkout();
    }

    public function test_security_rejections_are_audited_and_never_complete(): void
    {
        $payment = $this->checkout();
        $cases = [
            [$this->body($payment, ['amount_gross' => '9.99'], false), '192.0.2.1', 'invalid_signature'],
            [$this->body($payment, ['merchant_id' => '999']), '192.0.2.1', 'incorrect_merchant'],
            [$this->body($payment, ['amount_gross' => '9.99']), '192.0.2.1', 'incorrect_amount'],
            [$this->body($payment), '192.0.2.2', 'invalid_source'],
            [$this->body($payment, ['m_payment_id' => 'unknown']), '192.0.2.1', 'unknown_transaction'],
            [$this->body($payment, ['payment_status' => 'CANCELLED']), '192.0.2.1', 'unsupported_provider_status'],
        ];
        foreach ($cases as [$body, $ip, $error]) {
            try {
                app(NotificationService::class)->process($body, $ip);
                $this->fail('Expected rejection');
            } catch (PaymentException $e) {
                $this->assertSame($error, $e->getMessage());
            }
            $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        }
        $this->assertSame(count($cases), DB::table('payfast_events')->where('outcome', 'rejected')->count());
        Http::assertNothingSent();
    }

    public function test_provider_rejection(): void
    {
        Http::swap(new Factory);
        Http::fake(['*/eng/query/validate' => Http::response('INVALID')]);
        $payment = $this->checkout();
        $this->expectExceptionMessage('provider_validation_failed');
        app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
    }

    public function test_duplicate_and_late_itn_and_browser_return(): void
    {
        Event::fake([PaymentCompleted::class]);
        $payment = $this->checkout();
        $body = $this->body($payment);
        $this->assertSame('completed', app(NotificationService::class)->process($body, '192.0.2.1')->outcome);
        $this->assertSame('duplicate', app(NotificationService::class)->process($body, '192.0.2.1')->outcome);
        $this->get('/payments/payfast/return')->assertOk();
        $this->get('/payments/payfast/cancel')->assertOk();
        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame(1, DB::table('payfast_outbox')->count());
        Event::assertNotDispatched(PaymentCompleted::class);
        app(OutboxService::class)->dispatch();
        app(OutboxService::class)->dispatch();
        Event::assertDispatchedTimes(PaymentCompleted::class, 1);
    }

    public function test_return_before_notification_is_pending(): void
    {
        $payment = $this->checkout();
        $this->get('/payments/payfast/return')->assertOk()->assertSee('pending');
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_credential_rotation_keeps_original_verification(): void
    {
        $payment = $this->checkout();
        config(['payfast.passphrase' => 'rotated', 'payfast.merchant_id' => '999']);
        app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
    }

    public function test_default_resolver_rejects_tenant_context(): void
    {
        $this->expectException(PaymentException::class);
        $this->checkout('tenant-b');
    }

    public function test_tenant_cannot_fulfil_another_tenants_transaction(): void
    {
        $this->app->bind(MerchantResolver::class, fn () => new class implements MerchantResolver
        {
            public function resolve(string $context, ?string $tenantId): Merchant
            {
                return new Merchant('merchant-a', $tenantId, 'sandbox', '12345678', 'key', 'test passphrase', 'https://example.test/return', 'https://example.test/cancel', 'https://example.test/notify');
            }
        });
        $payment = $this->checkout('tenant-a');
        app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
        $this->expectException(ModelNotFoundException::class);
        app(FulfilmentService::class)->once($payment->id, 'merchant-a', 'tenant-b', 'orders', fn () => $this->fail('Cross-tenant work'));
    }

    public function test_fulfilment_retry_and_rollback(): void
    {
        $payment = $this->checkout();
        app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
        $service = app(FulfilmentService::class);
        try {
            $service->once($payment->id, 'default', null, 'orders', function () {
                DB::table('payfast_transactions')->update(['order_reference' => 'changed']);
                throw new \RuntimeException('retry');
            });
        } catch (\RuntimeException) {
            $this->assertNull($payment->fresh()->order_reference);
        }
        $this->assertSame(0, DB::table('payfast_fulfilments')->count());
        $count = 0;
        $work = function () use (&$count) {
            $count++;
        };
        $this->assertTrue($service->once($payment->id, 'default', null, 'orders', $work));
        $this->assertFalse($service->once($payment->id, 'default', null, 'orders', $work));
        $this->assertSame(1, $count);
    }

    public function test_database_failure_rolls_back_notification_and_retry_succeeds(): void
    {
        $payment = $this->checkout();
        DB::statement("CREATE TRIGGER fail_outbox BEFORE INSERT ON payfast_outbox BEGIN SELECT RAISE(ABORT, 'injected failure'); END");
        try {
            app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
            $this->fail('Expected DB failure');
        } catch (QueryException) {
            $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        }
        $this->assertSame(1, DB::table('payfast_events')->where('outcome', 'retryable_failure')->count());
        DB::statement('DROP TRIGGER fail_outbox');
        app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
    }

    public function test_routes_and_provider_configuration_publishing(): void
    {
        $this->assertInstanceOf(PaymentGateway::class, app(PaymentGateway::class));
        $this->assertNotEmpty(ServiceProvider::pathsToPublish(null, 'payfast-config'));
        $route = app('router')->getRoutes()->getByName('payfast.notify');
        $this->assertNotContains('web', $route->gatherMiddleware());
        $this->call('POST', '/payments/payfast/notify', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'REMOTE_ADDR' => '192.0.2.1'], 'bad=data')->assertStatus(400);
    }

    public function test_reconciliation_is_scoped(): void
    {
        $this->checkout();
        $this->assertCount(1, app(ReconciliationService::class)->pending('default', null, now()->addMinute()));
        $this->assertSame([], app(ReconciliationService::class)->pending('other', null, now()->addMinute()));
    }

    public function test_administrative_failure_and_cancellation_events_and_late_notification(): void
    {
        Event::fake();
        foreach ([PaymentStatus::Failed, PaymentStatus::Cancelled] as $status) {
            $payment = $this->checkout();
            app(PaymentLifecycleService::class)->close($payment->id, 'default', null, $status);
            $this->assertSame('ignored_terminal', app(NotificationService::class)->process($this->body($payment), '192.0.2.1')->outcome);
            $this->assertSame($status, $payment->fresh()->status);
        }
        app(OutboxService::class)->dispatch();
        Event::assertDispatchedTimes(PaymentFailed::class, 1);
        Event::assertDispatchedTimes(PaymentCancelled::class, 1);
        Event::assertNotDispatched(PaymentCompleted::class);
    }

    public function test_outbox_listener_failure_is_retryable(): void
    {
        $payment = $this->checkout();
        app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
        $attempts = 0;
        Event::listen(PaymentCompleted::class, function () use (&$attempts) {
            $attempts++;
            if ($attempts === 1) {
                throw new \RuntimeException('retry listener');
            }
        });
        try {
            app(OutboxService::class)->dispatch();
        } catch (\RuntimeException) {
            $this->assertNull(DB::table('payfast_outbox')->value('dispatched_at'));
        }
        app(OutboxService::class)->dispatch();
        $this->assertSame(2, $attempts);
        $this->assertNotNull(DB::table('payfast_outbox')->value('dispatched_at'));
    }

    public function test_provider_id_cannot_complete_two_transactions(): void
    {
        $first = $this->checkout();
        $second = $this->checkout();
        app(NotificationService::class)->process($this->body($first), '192.0.2.1');
        try {
            app(NotificationService::class)->process($this->body($second), '192.0.2.1');
            $this->fail('Expected collision rejection');
        } catch (QueryException) {
            $this->assertSame(PaymentStatus::Pending, $second->fresh()->status);
        }
        $this->assertSame(1, DB::table('payfast_outbox')->count());
    }

    public function test_cross_merchant_reference_swap_rejected(): void
    {
        $first = $this->checkout();
        config(['payfast.passphrase' => 'other-tenant-passphrase']);
        $second = $this->checkout();
        $this->expectExceptionMessage('invalid_signature');
        // Valid tenant A credentials cannot sign a notification for tenant B's stored snapshot.
        app(NotificationService::class)->process($this->body($first, ['m_payment_id' => $second->reference]), '192.0.2.1');
    }

    public function test_valid_itn_route_and_validation_post_body(): void
    {
        $payment = $this->checkout();
        $body = $this->body($payment);
        $this->call('POST', '/payments/payfast/notify', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'REMOTE_ADDR' => '192.0.2.1'], $body)->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.payfast.co.za/eng/query/validate' && $request->body() === explode('&signature=', $body)[0]);
        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
    }

    public function test_return_displays_status_only_after_host_authorization(): void
    {
        $payment = $this->checkout();
        $url = '/payments/payfast/return?payfast_reference='.$payment->reference;
        $this->get($url)->assertDontSee('Current payment status');
        $this->actingAs(new GenericUser(['id' => 1]));
        Gate::define('view-payfast-payment', fn ($user, $transaction) => $user->id === 1 && $transaction->id === $payment->id);
        $this->get($url)->assertSee('Current payment status: PENDING')->assertSee('Awaiting verified');
        app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
        $this->get($url)->assertSee('Current payment status: COMPLETED');
        $this->actingAs(new GenericUser(['id' => 2]));
        $this->get($url)->assertDontSee('Current payment status');
    }

    public function test_network_timeout_remains_retryable_and_audited(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => function () {
            throw new ConnectionException('simulated timeout');
        }]);
        $payment = $this->checkout();
        try {
            app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
            $this->fail('Expected timeout');
        } catch (ConnectionException) {
            $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        }
        $this->assertSame(1, DB::table('payfast_events')->where('outcome', 'retryable_failure')->count());
        $this->assertSame(0, DB::table('payfast_outbox')->count());
    }

    public function test_outbox_rejects_outer_transaction_before_delivery_marker_can_be_committed(): void
    {
        $payment = $this->checkout();
        app(NotificationService::class)->process($this->body($payment), '192.0.2.1');
        Event::fake([PaymentCompleted::class]);
        try {
            DB::transaction(fn () => app(OutboxService::class)->dispatch());
            $this->fail('Expected unsafe dispatch rejection');
        } catch (PaymentException) {
            $this->assertNull(DB::table('payfast_outbox')->value('dispatched_at'));
        }
        Event::assertNotDispatched(PaymentCompleted::class);
        app(OutboxService::class)->dispatch();
        Event::assertDispatchedTimes(PaymentCompleted::class, 1);
    }
}
