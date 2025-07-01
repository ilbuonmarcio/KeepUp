<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class MonitorServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor servers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $systems = Monitor::get();

        $results = array();

        foreach($systems as $system) {
            // Start gathering results about the host
            $result = array(
                'ran_as_user' => $system['username'],
                'auth_method' => $system['auth_method'],
                'connected_successfully' => false,
                'hostname_ip' => $system['hostname_ip'],
                'operating_system' => null,
                'updates_available' => null,
                'uptime' => null,
                'ip_addresses' => null
            );

            $process = Ssh::create($system['username'], $system['hostname_ip'])
                ->disableStrictHostKeyChecking()
                ->setTimeout(100);

            if($system['auth_method'] == 'password') {
                $process = $process->usePassword($system['password']);
            } elseif($system['auth_method'] == 'ssh_private_key') {
                $process = $process->usePrivateKey($system->sshPrivateKeyFullPath());
            } else {
                echo 'System ' . $system['hostname_ip'] . " has no auth method supported, skipping...\n";
                continue;
            }

            $request = $process->execute('cat /etc/*-release | grep "^NAME="');

            if(!$request->isSuccessful()) {
                echo 'System ' . $system['hostname_ip'] . " encountered an error, skipping...\n";
                $results[] = $result;

                continue; // Skip to next
            }

            $result['connected_successfully'] = true;
            $output = $request->getOutput();

            // Find out os name
            if (Str::contains($output, 'Debian')) {
                $result['operating_system'] = 'Debian';
            } elseif(Str::contains($output, 'Arch Linux')) {
                $result['operating_system'] = 'Arch Linux';
            }

            // Find out uptime and ip addresses
            if(collect(['Debian', 'Arch Linux'])->contains($result['operating_system'])) {
                $request = $process->execute('uptime --pretty');

                if($request->isSuccessful()) {
                    $result['uptime'] = Str::replace("\n", '', $request->getOutput());
                }

                $request = $process->execute("ip addr | grep \"inet \" | grep -v 'inet 127.0.0.1' | awk '{print $2}'");

                if($request->isSuccessful()) {
                    $result['ip_addresses'] = Str::of($request->getOutput())->explode("\n")->slice(0, -1)->toArray();
                }
            }

            // Find out how many updates do you have
            // Find out uptime
            if(collect(['Debian'])->contains($result['operating_system'])) {
                $request = $process->execute('apt update > /dev/null 2>&1; apt list --upgradable 2>/dev/null | tail -n +2 | wc -l');

                if($request->isSuccessful()) {
                    $result['updates_available'] = Str::replace("\n", '', $request->getOutput());
                }
            }

            if(!$result['connected_successfully']) {
                // Saving to database
                $system->latest_check_positive = 0;
                $system->operating_system = null;
                $system->updates_available = null;
                $system->uptime = null;
                $system->ip_addresses = null;
                $system->latest_successful_check = Carbon::now();
                $system->save();
            } else {
                // Saving to database
                $system->latest_check_positive = 1;
                $system->operating_system = $result['operating_system'];
                $system->updates_available = $result['updates_available'];
                $system->uptime = $result['uptime'];
                $system->ip_addresses = json_encode($result['ip_addresses']);
                $system->latest_successful_check = Carbon::now();
                $system->save();
            }
        }
    }
}
