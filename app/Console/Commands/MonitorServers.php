<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use Exception;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use App\Models\MonitorLastRefresh;

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

        foreach($systems as $system) {
            try {
                Log::channel('monitors_stacked')->info("Checking monitor for system [$system->name]");
                $init = Carbon::now();

                // Start gathering results about the host
                $result = array(
                    'ran_as_user' => $system['username'],
                    'auth_method' => $system['auth_method'],
                    'connected_successfully' => false,
                    'hostname_ip' => $system['hostname_ip'],
                    'operating_system' => null,
                    'operating_system_full_version' => null,
                    'updates_available' => null,
                    'uptime' => null,
                    'ip_addresses' => null,
                    'cpu_load' => null,
                    'disks_status' => null,
                    'docker_daemon_running' => null,
                    'docker_active_containers' => null
                );

                $process = Ssh::create($system['username'], $system['hostname_ip'])
                    ->disableStrictHostKeyChecking()
                    ->setTimeout(100);

                if($system['auth_method'] == 'password') {
                    $process = $process->usePassword(Crypt::decryptString($system['password']));
                } elseif($system['auth_method'] == 'ssh_private_key') {
                    // Decrypt ssh private key on the fly
                    $system->sshKeyDecrypt();
                    $process = $process->usePrivateKey($system->sshPrivateKeyFullPath() . '.decrypt')->disablePasswordAuthentication();
                } else {
                    echo 'System ' . $system['hostname_ip'] . " has no auth method supported, skipping...\n";
                    continue;
                }

                $request = $process->execute('cat /etc/*-release | grep "^PRETTY_NAME="');

                if(!$request->isSuccessful()) {
                    Log::channel('monitors_stacked')->error("Monitor for system [$system->name] encountered an error");
                } else {
                    $result['connected_successfully'] = true;
                }

                if($result['connected_successfully']) {
                    $output = $request->getOutput();

                    // Find out os name
                    if (Str::contains($output, 'Debian')) {
                        $result['operating_system'] = 'Debian';
                    } elseif(Str::contains($output, 'Arch Linux')) {
                        $result['operating_system'] = 'Arch Linux';
                    } elseif(Str::contains($output, 'Ubuntu')) {
                        $result['operating_system'] = 'Ubuntu';
                    }

                    // Save also os name full label for system version control and EOL
                    $result['operating_system_full_version'] = Str::replace("PRETTY_NAME=", "", Str::replace("\"", "", $output));

                    // Checks, if Debian, if it is a Proxmox server and overrides the info provided for the full version
                    if($result['operating_system'] == 'Debian') {
                        $request = $process->execute('[ -f /etc/pve/.version ] && echo "1" || echo "0"');

                        if($request->isSuccessful() && Str::contains($request->getOutput(), "1")) {
                            $request = $process->execute('pveversion');

                            if($request->isSuccessful()) {
                                $result['operating_system'] = 'Proxmox VE';
                                $result['operating_system_full_version'] = Str::replace("pve-manager/", "Proxmox VE ", Str::replace("\n", '', $request->getOutput()));
                            }
                        }
                    }

                    // Find out uptime and ip addresses
                    // Find out cpu load average
                    // Find out memory consumption
                    // Find out disk status
                    // Find out if docker daemon is running
                    if(collect(['Debian', 'Arch Linux', 'Ubuntu', 'Proxmox VE'])->contains($result['operating_system'])) {
                        $request = $process->execute('awk \'{printf "%.2f", $1/86400}\' /proc/uptime');

                        if($request->isSuccessful()) {
                            $result['uptime'] = Str::replace("\n", '', $request->getOutput());
                        }

                        $request = $process->execute("ip addr | grep \"inet \" | grep -v 'inet 127.0.0.1' | awk '{print $2}'");

                        if($request->isSuccessful()) {
                            $result['ip_addresses'] = Str::of($request->getOutput())->explode("\n")->slice(0, -1)->toArray();
                        }

                        $request = $process->execute('uptime | grep -ohe \'load average[s:][: ].*\' | awk \'{ print $3, $4, $5 }\'');

                        if($request->isSuccessful()) {
                            $result['cpu_load'] = Str::replace("\n", '', $request->getOutput());
                        }

                        $request = $process->execute('df -h | head -n 1; df -h | grep \'^/dev\' | grep -v \'^/dev/loop\'');

                        if($request->isSuccessful()) {
                            $result['disks_status'] = $request->getOutput();
                        }

                        $request = $process->execute('docker info >/dev/null 2>&1 && echo "1" || echo "0"');

                        if($request->isSuccessful()) {
                            $result['docker_daemon_running'] = Str::replace("\n", '', $request->getOutput());

                            if($result['docker_daemon_running'] == 1) {
                                $request = $process->execute('docker ps -q | wc -l');

                                if($request->isSuccessful()) {
                                    $result['docker_active_containers'] = Str::replace("\n", '', $request->getOutput());
                                }
                            }
                        }
                    }

                    // Find out how many updates do you have
                    if(collect(['Debian', 'Ubuntu', 'Proxmox VE'])->contains($result['operating_system'])) {
                        $request = $process->execute('apt update > /dev/null 2>&1; apt list --upgradable 2>/dev/null | tail -n +2 | wc -l');

                        if($request->isSuccessful()) {
                            $result['updates_available'] = Str::replace("\n", '', $request->getOutput());
                        }
                    }
                    if(collect(['Arch Linux'])->contains($result['operating_system'])) {
                        $request = $process->execute('pacman -Sy &> /dev/null;pacman -Qu | wc -l');

                        if($request->isSuccessful()) {
                            $result['updates_available'] = Str::replace("\n", '', $request->getOutput());
                        }
                    }
                }

                if(!$result['connected_successfully']) {
                    // Saving to database
                    $system->latest_check_positive = 0;
                    $system->operating_system = null;
                    $system->operating_system_full_version = null;
                    $system->updates_available = null;
                    $system->uptime = null;
                    $system->ip_addresses = null;
                    $system->cpu_load = null;
                    $system->disks_status = null;
                    $system->docker_daemon_running = null;
                    $system->docker_active_containers = null;
                } else {
                    // Saving to database
                    $system->latest_check_positive = 1;
                    $system->operating_system = $result['operating_system'];
                    $system->operating_system_full_version = $result['operating_system_full_version'];
                    $system->updates_available = $result['updates_available'];
                    $system->uptime = $result['uptime'];
                    $system->ip_addresses = json_encode($result['ip_addresses']);
                    $system->cpu_load = $result['cpu_load'];
                    $system->disks_status = $result['disks_status'];
                    $system->docker_daemon_running = $result['docker_daemon_running'];
                    $system->docker_active_containers = $result['docker_active_containers'];
                }

                $system->latest_successful_check = Carbon::now();
                $system->check_time = $init->diffInMilliseconds(Carbon::now());;
                $system->save();

                // Create version of the last saved monitor status, if connected successfully
                if($result['connected_successfully']) {
                    $system->version();
                }
                
                Log::channel('monitors_stacked')->info("Monitor for system [$system->name] checked in $system->check_time ms");
            } catch (Exception $e) {
                Log::channel('monitors_stacked')->error("Error while checking monitor for system [$system->name]");

                // Saving the failure
                $system->latest_check_positive = 0;
                $system->operating_system = null;
                $system->operating_system_full_version = null;
                $system->updates_available = null;
                $system->uptime = null;
                $system->ip_addresses = null;
                $system->cpu_load = null;
                $system->disks_status = null;
                $system->docker_daemon_running = null;
                $system->docker_active_containers = null;
                $system->save();
            }

            $system->sshKeyDecryptFlush();
        }

        // Add refresh status update
        $refresh = new MonitorLastRefresh();
        $refresh->save();
    }
}
