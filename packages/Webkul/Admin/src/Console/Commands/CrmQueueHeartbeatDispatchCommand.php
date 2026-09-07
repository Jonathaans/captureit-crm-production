<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Jobs\CrmQueueHeartbeatJob;

class CrmQueueHeartbeatDispatchCommand extends Command
{
    protected $signature = 'crm:health:queue-dispatch';
    protected $description = 'Dispatch a heartbeat that must be consumed by the queue worker.';

    public function handle(): int
    {
        CrmQueueHeartbeatJob::dispatch()->onQueue('default');

        return self::SUCCESS;
    }
}
