<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Webkul\Admin\Services\CrmOperationalHealthService;

class CrmManagedEmailSyncCommand extends Command
{
    protected $signature = 'crm:email-sync-managed';
    protected $description = 'Run My Email sync and record operational health.';

    public function handle(CrmOperationalHealthService $health): int
    {
        $started = hrtime(true);
        $health->started(CrmOperationalHealthService::EMAIL_SYNC, 'Sinkronisasi email dimulai.');

        try {
            $code = Artisan::call('my-email:sync');
            $output = trim(Artisan::output());
            $duration = (int) ((hrtime(true) - $started) / 1_000_000);

            if ($code !== self::SUCCESS) {
                $health->failure(CrmOperationalHealthService::EMAIL_SYNC, $output ?: 'Email sync gagal.', [], $duration);
                $this->error($output ?: 'Email sync gagal.');
                return self::FAILURE;
            }

            $health->success(CrmOperationalHealthService::EMAIL_SYNC, $output ?: 'Email sync berhasil.', [], $duration);
            $this->line($output);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $health->failure(
                CrmOperationalHealthService::EMAIL_SYNC,
                $exception->getMessage(),
                ['exception' => $exception::class],
                (int) ((hrtime(true) - $started) / 1_000_000)
            );
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
