<?php

namespace Vmt\PayFast\Console;

use Illuminate\Console\Command;
use Vmt\PayFast\Services\OutboxService;

final class DispatchOutbox extends Command
{
    protected $signature = 'payfast:dispatch-outbox';

    protected $description = 'Deliver committed payment outcomes to application listeners';

    public function handle(OutboxService $outbox): int
    {
        $this->info('Dispatched '.$outbox->dispatch().' payment outcomes.');

        return self::SUCCESS;
    }
}
