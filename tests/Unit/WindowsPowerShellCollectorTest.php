<?php

use App\Services\WindowsPowerShellCollector;

test('windows collector builds an encoded non-interactive powershell command', function () {
    $command = (new WindowsPowerShellCollector)->command();

    expect($command)
        ->toStartWith('powershell.exe -NoLogo -NoProfile -NonInteractive')
        ->toContain('-EncodedCommand ');
});

test('windows collector parses and normalizes powershell json', function () {
    $output = json_encode([
        'operating_system' => 'Windows',
        'operating_system_full_version' => 'Microsoft Windows Server 2022 10.0.20348 (Build 20348)',
        'updates_available' => 3,
        'uptime' => 12.25,
        'ip_addresses' => '192.168.1.20/24',
        'cpu_load' => 17.5,
        'disks_status' => 'Drive C:',
        'docker_daemon_running' => 1,
        'docker_active_containers' => 2,
        'firewall_rules' => ['Status: active', 'Domain: enabled'],
    ]);

    $result = (new WindowsPowerShellCollector)->parse($output);

    expect($result)
        ->not->toBeNull()
        ->and($result['operating_system'])->toBe('Windows')
        ->and($result['updates_available'])->toBe(3)
        ->and($result['uptime'])->toBe(12.25)
        ->and($result['ip_addresses'])->toBe(['192.168.1.20/24'])
        ->and($result['cpu_load'])->toBe('17.5')
        ->and($result['docker_active_containers'])->toBe(2);
});

test('windows collector accepts json independently of ssh stderr or exit status', function () {
    $result = (new WindowsPowerShellCollector)->parse('{"operating_system":"Windows","uptime":1}');

    expect($result)->not->toBeNull()
        ->and($result['uptime'])->toBe(1.0);
});

test('windows collector extracts json from the interactive cmd shell envelope', function () {
    $output = "Microsoft Windows [Version 10.0.26100.1]\r\n"
        ."C:\\Users\\keepup>powershell.exe -EncodedCommand abc123\r\n"
        .'{"operating_system":"Windows","updates_available":2}'
        ."\r\nC:\\Users\\keepup>";

    $result = (new WindowsPowerShellCollector)->parse($output);

    expect($result)->not->toBeNull()
        ->and($result['updates_available'])->toBe(2);
});

test('windows collector rejects invalid or non-windows output', function () {
    $collector = new WindowsPowerShellCollector;

    expect($collector->parse('not json'))->toBeNull()
        ->and($collector->parse('{"operating_system":"Linux"}'))->toBeNull();
});
