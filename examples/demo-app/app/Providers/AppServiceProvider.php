<?php

namespace App\Providers;

use App\Listeners\CompleteDemoOrder;
use App\Models\DemoOrder;
use App\Services\SandboxMerchantResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Vmt\PayFast\Contracts\MerchantResolver;
use Vmt\PayFast\Events\PaymentCompleted;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MerchantResolver::class, SandboxMerchantResolver::class);
    }

    public function boot(): void
    {
        config(['app.debug' => false, 'queue.connections.database.after_commit' => true]);
        Event::listen(PaymentCompleted::class, CompleteDemoOrder::class);
        Gate::define('view-payfast-payment', fn ($user, $payment) => $payment->merchant_identity === 'default' && $payment->tenant_id === null && DemoOrder::where('reference', $payment->order_reference)->where('user_id', $user->id)->exists());
    }
}
