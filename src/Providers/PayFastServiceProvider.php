<?php

namespace Vmt\PayFast\Providers;

use Illuminate\Support\ServiceProvider;
use Vmt\PayFast\Console\DispatchOutbox;
use Vmt\PayFast\Contracts\MerchantResolver;
use Vmt\PayFast\Contracts\PaymentGateway;
use Vmt\PayFast\Contracts\ProviderSource;
use Vmt\PayFast\Services\ConfigMerchantResolver;
use Vmt\PayFast\Services\DnsProviderSource;
use Vmt\PayFast\Services\PayFastGateway;

final class PayFastServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payfast.php', 'payfast');
        $this->app->bindIf(MerchantResolver::class, ConfigMerchantResolver::class);
        $this->app->bindIf(ProviderSource::class, DnsProviderSource::class);
        $this->app->bindIf(PaymentGateway::class, PayFastGateway::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishes([__DIR__.'/../../config/payfast.php' => config_path('payfast.php')], 'payfast-config');
        if (config('payfast.routes')) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        }
        if ($this->app->runningInConsole()) {
            $this->commands([DispatchOutbox::class]);
        }
    }
}
