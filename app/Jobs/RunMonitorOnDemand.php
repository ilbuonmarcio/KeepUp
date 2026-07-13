<?php

namespace App\Jobs;

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RunMonitorOnDemand implements ShouldQueue
{
    use Queueable;

    public ?int $monitorId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $monitorId = null)
    {
        $this->monitorId = $monitorId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Jobs queued before per-monitor scans were introduced have no
        // monitorId property in their serialized payload.
        $monitorId = $this->monitorId ?? null;
        $parameters = ['--force' => true];

        if ($monitorId !== null) {
            $parameters['--monitor'] = $monitorId;
        }

        if (Artisan::call('app:monitor', $parameters) !== Command::SUCCESS) {
            throw new RuntimeException('The requested monitor scan failed.');
        }
    }
}
