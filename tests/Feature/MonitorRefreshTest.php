<?php

use App\Jobs\RunMonitorOnDemand;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

function createMonitorForRefresh(): Monitor
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

test('a user can request a refresh for a specific monitor', function () {
    Bus::fake();

    $user = User::factory()->create();
    $monitor = createMonitorForRefresh();

    $this->actingAs($user)
        ->post(route('monitors.refresh', $monitor))
        ->assertStatus(202)
        ->assertJson(['status' => true]);

    Bus::assertDispatched(
        RunMonitorOnDemand::class,
        fn (RunMonitorOnDemand $job) => $job->monitorId === $monitor->getKey(),
    );
});

test('a user can request a scan for all monitors', function () {
    Bus::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('monitors.run-ondemand'))
        ->assertRedirect('/');

    Bus::assertDispatched(
        RunMonitorOnDemand::class,
        fn (RunMonitorOnDemand $job) => $job->monitorId === null,
    );
});

test('guests cannot request a monitor refresh', function () {
    Bus::fake();

    $monitor = createMonitorForRefresh();

    $this->post(route('monitors.refresh', $monitor))
        ->assertRedirect(route('login'));

    Bus::assertNothingDispatched();
});

test('the refresh job scans only its requested monitor', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('app:monitor', ['--force' => true, '--monitor' => 42])
        ->andReturn(Command::SUCCESS);

    (new RunMonitorOnDemand(42))->handle();
});

test('refresh jobs queued before monitor targeting remain compatible', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('app:monitor', ['--force' => true])
        ->andReturn(Command::SUCCESS);

    $legacyJob = unserialize('O:27:"App\\Jobs\\RunMonitorOnDemand":0:{}');

    $legacyJob->handle();
});
