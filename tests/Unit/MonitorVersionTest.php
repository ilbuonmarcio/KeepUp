<?php

use App\Models\Monitor;
use App\Models\MonitorVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a successful partial scan can be versioned when a metric is unavailable', function () {
    $monitor = new Monitor;
    $monitor->name = 'Windows server';
    $monitor->hostname_ip = '192.0.2.10';
    $monitor->username = 'keepup';
    $monitor->auth_method = 'password';
    $monitor->uptime = 2.5;
    $monitor->updates_available = null;
    $monitor->check_time = 1200;
    $monitor->save();

    $monitor->version();

    $version = MonitorVersion::query()->where('monitor_id', $monitor->id)->sole();

    expect($version->uptime)->toEqual(2.5)
        ->and($version->updates_available)->toBeNull();
});
