<?php

use App\Models\Monitor;
use Illuminate\Support\Carbon;

test('a successful check records the time of the latest success', function () {
    $monitor = new Monitor;
    $checkedAt = Carbon::parse('2026-07-22 10:30:00');

    $monitor->markCheckSuccessful($checkedAt);

    expect($monitor->latest_check_positive)->toBe(1)
        ->and($monitor->latest_successful_check)->toBe($checkedAt);
});

test('a failed check preserves the time of the latest successful check', function () {
    $monitor = new Monitor;
    $lastSuccess = Carbon::parse('2026-07-21 08:00:00');
    $monitor->latest_check_positive = 1;
    $monitor->latest_successful_check = $lastSuccess;

    $monitor->markCheckFailed();

    expect($monitor->latest_check_positive)->toBe(0)
        ->and($monitor->latest_successful_check)->toBe($lastSuccess);
});
