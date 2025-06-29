<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class Playground extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:playground';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $systems = [
            [
                'user' => 'mrcz',
                'hostname' => 'keepup',
                'auth_method' => 'password',
                'password' => '123'
            ],
            [
                'user' => 'root',
                'hostname' => 'sorriso.cloud',
                'auth_method' => 'ssh_private_key',
                'ssh_private_key_path' => ''
            ]
        ];

        $results = array();

        foreach($systems as $system) {
            // Start gathering results about the host
            $result = array(
                'ran_as_user' => $system['user'],
                'auth_method' => $system['auth_method'],
                'connected_successfully' => false,
                'hostname' => $system['hostname'],
                'os_name' => null,
                'updates_available' => null,
                'uptime' => null,
                'ip_addresses' => null
            );

            $process = Ssh::create($system['user'], $system['hostname'])
                ->disableStrictHostKeyChecking();

            if($system['auth_method'] == 'password') {
                $process = $process->usePassword($system['password']);
            } elseif($system['auth_method'] == 'ssh_private_key') {
                $process = $process->usePrivateKey($system['ssh_private_key_path']);
            } else {
                echo 'System ' . $system['hostname'] . " has no auth method supported, skipping...\n";
                continue;
            }

            $request = $process->execute('cat /etc/*-release | grep "^NAME="');

            if(!$request->isSuccessful()) {
                echo 'System ' . $system['hostname'] . " encountered an error, skipping...\n";
                $results[] = $result;

                continue; // Skip to next
            }

            $result['connected_successfully'] = true;
            $output = $request->getOutput();

            // Find out os name
            if (Str::contains($output, 'Debian')) {
                $result['os_name'] = 'Debian';
            } elseif(Str::contains($output, 'Arch Linux')) {
                $result['os_name'] = 'Arch Linux';
            }

            // Find out uptime and ip addresses
            if(collect(['Debian', 'Arch Linux'])->contains($result['os_name'])) {
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
            if(collect(['Debian'])->contains($result['os_name'])) {
                $request = $process->execute('apt update > /dev/null 2>&1; apt list --upgradable 2>/dev/null | tail -n +2 | wc -l');

                if($request->isSuccessful()) {
                    $result['updates_available'] = Str::replace("\n", '', $request->getOutput());
                }
            }

            // echo 'System "' . $result['hostname'] . '": OS found is ' . $result['os_name'] . ", updates avail.: " . $result['updates_available'] . ", uptime: " . $result['uptime'] . "\n";
            $results[] = $result;
        }

        dd($results);
    }
}
