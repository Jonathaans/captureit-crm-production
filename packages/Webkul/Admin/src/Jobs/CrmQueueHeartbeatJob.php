<?php

namespace Webkul\Admin\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Webkul\Admin\Services\CrmOperationalHealthService;

class CrmQueueHeartbeatJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;
    public int $uniqueFor = 300;

    public function handle(CrmOperationalHealthService $health): void
    {
        $health->success(
            CrmOperationalHealthService::QUEUE,
            'Queue worker memproses heartbeat.',
            ['connection' => config('queue.default'), 'queue' => $this->queue ?: 'default']
        );
    }
}
