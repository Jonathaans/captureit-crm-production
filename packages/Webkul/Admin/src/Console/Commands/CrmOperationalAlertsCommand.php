<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Services\CrmOperationalAlertService;

class CrmOperationalAlertsCommand extends Command
{
    protected $signature = 'crm:operations-alerts';
    protected $description = 'Generate operational health, stock, missing, and damaged alerts.';

    public function handle(CrmOperationalAlertService $alerts): int
    {
        $result = $alerts->generate();
        $this->info('Operational alerts: active='.$result['created_or_updated'].' resolved='.$result['resolved']);

        return self::SUCCESS;
    }
}
