<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
/* CRM_SENDGRID_BULK_SCHEDULER_GUARD_V1
 * SendGrid receives inbound messages through its webhook and does not support
 * a bulk mailbox pull. Do not run the bulk command for that receiver.
 */
if (config('mail-receiver.default', 'sendgrid') !== 'sendgrid') {
    Schedule::command('inbound-emails:process')
        ->everyFiveMinutes()
        ->withoutOverlapping(10);
}

// MY EMAIL PERSONAL AUTO SYNC V2 START
Artisan::command('my-email:sync', function () {
    $sync =
        app(
            \Webkul\Admin\Services\UserEmailSyncService::class
        );

    $accounts =
        \Webkul\Admin\Models\UserEmailAccount::query()
            ->where(
                'sync_enabled',
                true
            )
            ->orderBy('id')
            ->get();

    if ($accounts->isEmpty()) {
        $this->warn(
            'Tidak ada My Email account dengan sync_enabled=1.'
        );

        return 0;
    }

    $totalNew =
        0;

    $failed =
        0;

    foreach ($accounts as $account) {
        try {
            $count =
                $sync->sync(
                    $account,
                    100
                );

            $totalNew +=
                $count;

            $this->info(
                sprintf(
                    '[OK] %s | new=%d | last_synced_at=%s',
                    $account->email_address,
                    $count,
                    (string) (
                        $account
                            ->fresh()
                            ?->last_synced_at
                        ?? '-'
                    )
                )
            );
        } catch (\Throwable $exception) {
            $failed++;

            $this->error(
                sprintf(
                    '[FAIL] %s | %s',
                    $account->email_address,
                    $exception->getMessage()
                )
            );
        }
    }

    $this->line(
        sprintf(
            'My Email sync complete: accounts=%d new=%d failed=%d',
            $accounts->count(),
            $totalNew,
            $failed
        )
    );

    return $failed > 0
        ? 1
        : 0;
})
    ->purpose(
        'Sync Personal My Email accounts using the same service as Sync Now'
    );


// MY EMAIL PERSONAL AUTO SYNC V2 END


/* CRM_PRODUCTION_OPERATIONS_V2
 * Application schedules. The operating system must run artisan schedule:run
 * every minute, and a separate long-running queue:work process must be active.
 */
$crmOperationsTimezone = (string) config('app.timezone', 'Asia/Jakarta');

Schedule::command('crm:health:scheduler')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('crm:health:queue-dispatch')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('crm:email-sync-managed')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/crm-email-sync.log'));

Schedule::command('crm:backup-managed --database-only')
    ->dailyAt((string) config('crm-production-operations.backup.daily_database_time', '02:00'))
    ->timezone($crmOperationsTimezone)
    ->withoutOverlapping(360)
    ->appendOutputTo(storage_path('logs/crm-database-backup.log'));

Schedule::command('crm:backup-managed')
    ->weeklyOn(
        (int) config('crm-production-operations.backup.weekly_full_day', 0),
        (string) config('crm-production-operations.backup.weekly_full_time', '03:00')
    )
    ->timezone($crmOperationsTimezone)
    ->withoutOverlapping(720)
    ->appendOutputTo(storage_path('logs/crm-full-backup.log'));

Schedule::command('crm:backup-retention')
    ->dailyAt('04:00')
    ->timezone($crmOperationsTimezone)
    ->withoutOverlapping(30);

Schedule::command('crm:operations-alerts')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);
