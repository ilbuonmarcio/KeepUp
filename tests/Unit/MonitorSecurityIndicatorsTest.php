<?php

use App\Models\Monitor;

test('public IP detection distinguishes public and private addresses', function () {
    $monitor = new Monitor;
    $monitor->ip_addresses = json_encode(['10.0.0.5/24', '8.8.8.8/32']);

    expect($monitor->hasPublicIp())->toBeTrue();

    $monitor->ip_addresses = json_encode(['10.0.0.5/24', '192.168.1.10/24']);

    expect($monitor->hasPublicIp())->toBeFalse();
});

test('firewall detection requires an active firewall status', function () {
    $monitor = new Monitor;
    $monitor->firewall_rules = json_encode(['Status: active', '22 ALLOW IN Anywhere']);

    expect($monitor->firewallIsActive())->toBeTrue();

    $monitor->firewall_rules = json_encode(['Status: inactive']);

    expect($monitor->firewallIsActive())->toBeFalse();

    $monitor->firewall_rules = null;

    expect($monitor->firewallIsActive())->toBeFalse();
});

test('windows firewall profiles use the shared active status marker', function () {
    $monitor = new Monitor;
    $monitor->operating_system = 'Windows';
    $monitor->firewall_rules = json_encode(['Status: active', 'Domain: enabled', 'Public: enabled']);

    expect($monitor->firewallIsActive())->toBeTrue()
        ->and($monitor->firewallRules())->toContain('Domain: enabled');
});
