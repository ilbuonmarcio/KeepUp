<?php

namespace App\Services;

use Spatie\Ssh\Ssh;

class OpnsenseCollector
{
    public function collect(Ssh $ssh): ?array
    {
        // Explicit sh works without bash and with a csh login shell.
        $run = function (string $command) use ($ssh): ?string {
            $request = $ssh->execute('/bin/sh -c '.escapeshellarg('export LC_ALL=C; '.$command));

            return $request->isSuccessful() ? trim($request->getOutput()) : null;
        };

        $version = $run('/usr/local/sbin/opnsense-version -v');
        if ($version === null || ! preg_match('/^\d+\.\d+[^\r\n]*$/', $version)) {
            return null;
        }

        $result = [
            'operating_system' => 'OPNsense',
            'operating_system_full_version' => 'OPNsense '.$version,
            'updates_available' => null,
            'uptime' => null,
            'ip_addresses' => null,
            'cpu_load' => null,
            'disks_status' => $run('/bin/df -h'),
            'firewall_rules' => null,
        ];

        $boot = $run('/sbin/sysctl -n kern.boottime');
        $now = $run('/bin/date +%s');
        if ($boot !== null && preg_match('/sec = (\d+)/', $boot, $matches) && ctype_digit($now ?? '') && (int) $now >= (int) $matches[1]) {
            $result['uptime'] = round(((int) $now - (int) $matches[1]) / 86400, 2);
        }

        $load = $run('/sbin/sysctl -n vm.loadavg');
        if ($load !== null) {
            $result['cpu_load'] = trim($load, '{} ');
        }

        $interfaces = $run('/sbin/ifconfig -a');
        if ($interfaces !== null) {
            preg_match_all('/\binet (\d+\.\d+\.\d+\.\d+)\b/', $interfaces, $matches);
            $result['ip_addresses'] = array_values(array_unique(array_filter($matches[1], fn ($ip) => ! str_starts_with($ip, '127.'))));
        }

        $status = $run('/sbin/pfctl -s info');
        if ($status !== null && preg_match('/^Status: (Enabled|Disabled)\b/m', $status, $matches)) {
            $result['firewall_rules'] = ['Status: '.($matches[1] === 'Enabled' ? 'active' : 'inactive')];
            $rules = $run('/sbin/pfctl -s rules');
            if ($rules !== null && $rules !== '') {
                $result['firewall_rules'] = array_merge($result['firewall_rules'], preg_split('/\R/', $rules));
            }
        }

        // Read the last firmware check; never invoke upgrades or change the appliance.
        $cache = json_decode($run('/bin/cat /tmp/pkg_upgrade.json') ?? '', true);
        if (is_array($cache) && ($cache['connection'] ?? null) === 'ok' && ($cache['repository'] ?? null) === 'ok' && is_array($cache['upgrade_packages'] ?? null)) {
            $result['updates_available'] = count($cache['upgrade_packages']);
        }

        return $result;
    }
}
