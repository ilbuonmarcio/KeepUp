<?php

use App\Models\Monitor;
use App\Models\MonitorLastRefresh;
use Illuminate\Console\Command;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function createMonitorForConcurrency(): Monitor
{
    $monitor = new Monitor;
    $monitor->name = 'Test server';
    $monitor->hostname_ip = '192.0.2.1';
    $monitor->username = 'keepup';
    $monitor->auth_method = 'password';
    $monitor->password = 'encrypted-password';
    $monitor->save();

    return $monitor;
}

test('parallel scans target each server and record one completed refresh', function () {
    Process::fake();
    $monitors = collect(range(1, 5))->map(fn () => createMonitorForConcurrency());

    $this->artisan('app:monitor', ['--concurrency' => 2, '--force' => true])
        ->assertSuccessful();

    Process::assertRanTimes(fn () => true, 5);
    foreach ($monitors as $monitor) {
        Process::assertRan(fn (PendingProcess $process) => $process->command === [
            PHP_BINARY, base_path('artisan'), 'app:monitor',
            '--monitor='.$monitor->getKey(), '--no-refresh', '--force',
        ]);
    }
    expect(MonitorLastRefresh::count())->toBe(1);
});

test('parallel worker failure fails the scan without recording completion', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Worker failed', exitCode: 1)]);
    createMonitorForConcurrency();

    $this->artisan('app:monitor', ['--concurrency' => 2])->assertFailed();

    expect(MonitorLastRefresh::count())->toBe(0);
});

test('invalid concurrency is rejected before starting workers', function ($concurrency) {
    Process::fake();

    $this->artisan('app:monitor', ['--concurrency' => $concurrency])
        ->assertExitCode(Command::INVALID);

    Process::assertNothingRan();
    expect(MonitorLastRefresh::count())->toBe(0);
})->with(['0', '-1', 'abc', '1.5']);

test('targeted worker skips recent monitors and does not record global completion', function () {
    Process::fake();
    $monitor = createMonitorForConcurrency();
    $monitor->operating_system = 'Debian';
    $monitor->save();

    $this->artisan('app:monitor', ['--monitor' => $monitor->getKey(), '--no-refresh' => true])
        ->assertSuccessful();

    Process::assertNothingRan();
    expect(MonitorLastRefresh::count())->toBe(0);
});

test('a free worker slot is reused before a slower monitor finishes', function () {
    $monitors = collect(range(1, 3))->map(fn () => createMonitorForConcurrency());
    $started = 0;
    Process::fake(function () use (&$started) {
        $started++;

        return Process::describe()->iterations($started === 1 ? 4 : 0);
    });

    $this->withoutMockingConsoleOutput();
    $exitCode = Illuminate\Support\Facades\Artisan::call('app:monitor', ['--concurrency' => 2]);
    $output = Illuminate\Support\Facades\Artisan::output();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($started)->toBe(3)
        ->and(strpos($output, 'Starting monitor '.$monitors[2]->getKey()))
        ->toBeLessThan(strpos($output, 'Finished monitor '.$monitors[0]->getKey()));
});
