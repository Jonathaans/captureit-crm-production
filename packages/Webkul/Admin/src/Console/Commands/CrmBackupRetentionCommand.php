<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Admin\Services\CrmBackupRetentionService;

class CrmBackupRetentionCommand extends Command
{
    protected $signature = 'crm:backup-retention';
    protected $description = 'Apply daily/weekly/monthly/yearly CRM backup retention.';

    public function handle(CrmBackupRetentionService $retention): int
    {
        $result = $retention->apply();
        $this->info(sprintf(
            'Retention selesai: kept=%d deleted=%d bytes_freed=%d',
            $result['kept'],
            $result['deleted'],
            $result['bytes_freed']
        ));

        return self::SUCCESS;
    }
}
