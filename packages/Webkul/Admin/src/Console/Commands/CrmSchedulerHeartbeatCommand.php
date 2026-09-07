<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Services\CrmOperationalHealthService;

class CrmSchedulerHeartbeatCommand extends Command
{
    protected $signature = 'crm:health:scheduler';
    protected $description = 'Record the Laravel scheduler heartbeat.';

    public function handle(CrmOperationalHealthService $health): int
    {
        $health->success(
            CrmOperationalHealthService::SCHEDULER,
            'Laravel scheduler berjalan.',
            ['host' => gethostname() ?: null]
        );

        return self::SUCCESS;
    }
}
