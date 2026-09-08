<?php

use App\Services\OpnsenseCollector;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

function opnsenseSsh(array $outputs): Ssh
{
    $ssh = Mockery::mock(Ssh::class);
    $ssh->shouldReceive('execute')->andReturnUsing(function ($command) use ($outputs) {
        foreach ($outputs as $fragment => $output) {
            if (str_contains($command, $fragment)) {
                $process = Mockery::mock(Process::class);
                $process->shouldReceive('isSuccessful')->andReturn($output !== null);
                $process->shouldReceive('getOutput')->andReturn($output ?? '');

                return $process;
            }
        }

        throw new RuntimeException('Unexpected command: '.$command);
    });

    return $ssh;
}

function opnsenseOutputs(): array
{
    return [
        'opnsense-version -v' => "26.1.1\n",
        '/bin/df -h' => 'Filesystem Size Used Avail Capacity Mounted on',
        'kern.boottime' => '{ sec = 172800, usec = 0 }',
        '/bin/date' => '388800',
        'vm.loadavg' => '{ 0.12 0.34 0.56 }',
        'ifconfig -a' => "lo0: flags\n inet 127.0.0.1 netmask 0xff000000\n inet 192.0.2.1 netmask 0xffffff00\n inet 192.0.2.1 netmask 0xffffff00\n inet6 ::1",
        'pfctl -s info' => 'Status: Enabled for 2 days',
        'pfctl -s rules' => 'pass in all flags S/SA keep state',
        '/tmp/pkg_upgrade.json' => '{"connection":"ok","repository":"ok","upgrade_packages":[{"name":"opnsense"}]}',
    ];
}

test('opnsense collects FreeBSD metrics and normalizes PF status', function () {
    $result = (new OpnsenseCollector)->collect(opnsenseSsh(opnsenseOutputs()));

    expect($result['operating_system'])->toBe('OPNsense')
        ->and($result['operating_system_full_version'])->toBe('OPNsense 26.1.1')
        ->and($result['uptime'])->toBe(2.5)
        ->and($result['cpu_load'])->toBe('0.12 0.34 0.56')
        ->and($result['ip_addresses'])->toBe(['192.0.2.1'])
        ->and($result['firewall_rules'])->toBe(['Status: active', 'pass in all flags S/SA keep state'])
        ->and($result['updates_available'])->toBe(1);
});

test('opnsense tolerates unavailable optional metrics', function () {
    $outputs = array_fill_keys(array_keys(opnsenseOutputs()), null);
    $outputs['opnsense-version -v'] = '26.1';
    $result = (new OpnsenseCollector)->collect(opnsenseSsh($outputs));

    expect($result['operating_system'])->toBe('OPNsense');
    foreach (['uptime', 'cpu_load', 'ip_addresses', 'firewall_rules', 'updates_available', 'disks_status'] as $field) {
        expect($result[$field])->toBeNull();
    }
});

test('opnsense rejects missing or invalid detection', function ($version) {
    expect((new OpnsenseCollector)->collect(opnsenseSsh(['opnsense-version -v' => $version])))->toBeNull();
})->with([null, '', 'command not found', 'FreeBSD 14.3']);

test('opnsense distinguishes unavailable updates from zero updates', function ($cache, $expected) {
    $outputs = opnsenseOutputs();
    $outputs['/tmp/pkg_upgrade.json'] = $cache;
    expect((new OpnsenseCollector)->collect(opnsenseSsh($outputs))['updates_available'])->toBe($expected);
})->with([
    ['{"connection":"ok","repository":"ok","upgrade_packages":[]}', 0],
    ['{"connection":"error","repository":"ok","upgrade_packages":[]}', null],
    ['{"connection":"ok","repository":"error","upgrade_packages":[]}', null],
    ['{}', null],
    ['invalid json', null],
    [null, null],
]);
