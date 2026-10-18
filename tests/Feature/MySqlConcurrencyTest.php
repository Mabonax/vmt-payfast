<?php

namespace Vmt\PayFast\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Vmt\PayFast\Contracts\PaymentGateway;
use Vmt\PayFast\Contracts\ProviderSource;
use Vmt\PayFast\Data\CheckoutRequest;
use Vmt\PayFast\Enums\PaymentStatus;
use Vmt\PayFast\Models\PaymentTransaction;
use Vmt\PayFast\Services\FulfilmentService;
use Vmt\PayFast\Services\NotificationService;
use Vmt\PayFast\Services\SignatureService;
use Vmt\PayFast\Tests\TestCase;

final class MySqlConcurrencyTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        if (getenv('PAYFAST_TEST_MYSQL') === '1') {
            $database = getenv('PAYFAST_TEST_DB_NAME') ?: 'payfast_test';
            if (! preg_match('/^payfast_test(?:_[a-z0-9]+)?$/D', $database)) {
                throw new \RuntimeException('Concurrency acceptance requires a dedicated payfast_test database.');
            }
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.mysql', [
                'driver' => 'mysql', 'host' => getenv('PAYFAST_TEST_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('PAYFAST_TEST_DB_PORT') ?: '3306',
                'database' => $database,
                'username' => getenv('PAYFAST_TEST_DB_USER') ?: 'payfast_test',
                'password' => getenv('PAYFAST_TEST_DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
            ]);
        }
    }

    public function test_two_process_duplicate_itn_and_fulfilment(): void
    {
        if (getenv('PAYFAST_TEST_MYSQL') !== '1' || getenv('PAYFAST_CONCURRENCY_WORKER')) {
            $this->markTestSkipped('Enable PAYFAST_TEST_MYSQL=1 with a dedicated disposable MySQL database.');
        }
        $this->artisan('migrate', ['--database' => 'mysql'])->run();
        $checkout = app(PaymentGateway::class)->createCheckout(new CheckoutRequest('concurrent-order', 105, 'Item'));
        $payment = PaymentTransaction::where('reference', $checkout->reference)->firstOrFail();
        $env = array_merge(getenv(), ['PAYFAST_CONCURRENCY_WORKER' => '1', 'PAYFAST_TEST_REFERENCE' => $payment->reference, 'PAYFAST_TEST_START' => (string) (microtime(true) + 3)]);
        $processes = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, 'vendor/bin/phpunit', '--filter', 'MySqlConcurrencyTest::test_worker', '--no-progress'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $env);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }
            foreach ($processes as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $output);
            }
            $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
            $this->assertSame(1, DB::table('payfast_outbox')->where('transaction_id', $payment->id)->count());
            $this->assertSame(1, DB::table('payfast_fulfilments')->where('transaction_id', $payment->id)->count());
            $this->assertSame(1, DB::table('payfast_events')->where('transaction_id', $payment->id)->where('outcome', 'completed')->count());
            $this->assertSame(1, DB::table('payfast_events')->where('transaction_id', $payment->id)->where('outcome', 'duplicate')->count());
        } finally {
            foreach (['payfast_fulfilments', 'payfast_outbox', 'payfast_events'] as $table) {
                DB::table($table)->where('transaction_id', $payment->id)->delete();
            }
            $payment->delete();
        }
    }

    public function test_worker(): void
    {
        if (getenv('PAYFAST_CONCURRENCY_WORKER') !== '1') {
            $this->markTestSkipped('Invoked only by the two-process MySQL supervisor test.');
        }
        $this->app->bind(ProviderSource::class, fn () => new class implements ProviderSource
        {
            public function accepts(string $ip): bool
            {
                return $ip === '192.0.2.1';
            }
        });
        Http::preventStrayRequests();
        Http::fake(['*/eng/query/validate' => function () {
            usleep(300000); // Both processes validate before competing for the row lock.

            return Http::response('VALID');
        }]);
        $payment = PaymentTransaction::where('reference', getenv('PAYFAST_TEST_REFERENCE'))->firstOrFail();
        $fields = ['m_payment_id' => $payment->reference, 'pf_payment_id' => (string) random_int(100000000, 999999999), 'payment_status' => 'COMPLETE', 'amount_gross' => '1.05', 'merchant_id' => '12345678'];
        // Derive a shared numeric provider reference for the two workers.
        $fields['pf_payment_id'] = (string) hexdec(substr(md5($payment->reference), 0, 10));
        $fields['signature'] = app(SignatureService::class)->notification($fields, 'test passphrase');
        while (microtime(true) < (float) getenv('PAYFAST_TEST_START')) {
            usleep(10000);
        }
        $result = app(NotificationService::class)->process(http_build_query($fields), '192.0.2.1');
        $this->assertContains($result->outcome, ['completed', 'duplicate']);
        app(FulfilmentService::class)->once($payment->id, 'default', null, 'concurrent-order', function ($locked) {
            $this->assertNull($locked->order_reference);
            $locked->order_reference = 'fulfilled';
            $locked->save();
        });
    }
}
