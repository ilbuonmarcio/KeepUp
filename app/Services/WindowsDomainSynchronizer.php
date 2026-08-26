<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\WindowsDomain;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class WindowsDomainSynchronizer
{
    public function __construct(private WindowsDomainDiscoveryService $discovery) {}

    public function sync(WindowsDomain $domain): int
    {
        try {
            $computers = $this->discovery->discover($domain);
            $hostnames = collect($computers)->pluck('hostname')->all();

            DB::transaction(function () use ($domain, $computers, $hostnames): void {
                foreach ($computers as $computer) {
                    $monitor = Monitor::query()
                        ->where('windows_domain_id', $domain->getKey())
                        ->where('hostname_ip', $computer['hostname'])
                        ->first() ?? new Monitor;
                    $monitor->windows_domain_id = $domain->getKey();
                    $monitor->hostname_ip = $computer['hostname'];
                    $monitor->name = $computer['name'];
                    $monitor->username = $domain->monitor_username;
                    $monitor->auth_method = $domain->auth_method;
                    $monitor->password = $domain->auth_method === 'password'
                        ? $this->inheritedPassword($monitor, $domain->monitor_password)
                        : null;
                    $monitor->ssh_private_key = $domain->auth_method === 'ssh_private_key'
                        ? $domain->ssh_private_key
                        : null;
                    $monitor->threshold_uptime = $domain->threshold_uptime;
                    $monitor->threshold_updates_available = $domain->threshold_updates_available;

                    if (! $monitor->exists || $monitor->isDirty()) {
                        $monitor->save();
                    }
                }

                $stale = $domain->monitors();
                if ($hostnames !== []) {
                    $stale->whereNotIn('hostname_ip', $hostnames);
                }
                $stale->get()->each->delete();

                $domain->forceFill([
                    'last_discovered_at' => now(),
                    'last_error' => null,
                ])->saveQuietly();
            });

            return count($computers);
        } catch (Throwable $exception) {
            $domain->forceFill(['last_error' => $exception->getMessage()])->saveQuietly();

            throw $exception;
        }
    }

    private function inheritedPassword(Monitor $monitor, string $password): string
    {
        if ($monitor->exists && filled($monitor->password)) {
            try {
                if (hash_equals($password, Crypt::decryptString($monitor->password))) {
                    return $monitor->password;
                }
            } catch (Throwable) {
                // Replace credentials that cannot be decrypted with the current application key.
            }
        }

        return Crypt::encryptString($password);
    }
}
