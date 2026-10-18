<?php

namespace Vmt\PayFast\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vmt\PayFast\Providers\PayFastServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PayFastServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $app['config']->set('payfast', [
            'mode' => 'sandbox', 'merchant_id' => '12345678', 'merchant_key' => 'test-key', 'passphrase' => 'test passphrase',
            'return_url' => 'https://example.test/payments/payfast/return',
            'cancel_url' => 'https://example.test/payments/payfast/cancel',
            'notify_url' => 'https://example.test/payments/payfast/notify', 'routes' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }
}
