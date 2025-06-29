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
                'user' => 'root',
                'hostname' => 'keepup',
                'password' => '123'
            ]
        ];

        $results = array();

        foreach($systems as $system) {
            $process = Ssh::create($system['user'], $system['hostname'])
                ->usePassword($system['password'])
                ->disableStrictHostKeyChecking()
                ->execute('cat /etc/*-release | grep "^NAME="');

            if(!$process->isSuccessful()) {
                echo 'System ' . $system['hostname'] . " encountered an error, skipping...\n";
                continue;
            }

            $output = $process->getOutput();

            // Start gathering results about the host
            $result = array(
                'ran_as_user' => $system['user'],
                'hostname' => $system['hostname'],
                'os_name' => null,
                'updates_available' => null,
                'uptime' => null,
                'ip_addresses' => null
            );

            // Find out os name
            if (Str::contains($output, 'Debian')) {
                $result['os_name'] = 'Debian';
            } elseif(Str::contains($output, 'Arch Linux')) {
                $result['os_name'] = 'Arch Linux';
            }

            // Find out uptime and ip addresses
            if(collect(['Debian', 'Arch Linux'])->contains($result['os_name'])) {
                $process = Ssh::create($system['user'], $system['hostname'])
                    ->usePassword($system['password'])
                    ->disableStrictHostKeyChecking()
                    ->execute('uptime --pretty');

                if($process->isSuccessful()) {
                    $result['uptime'] = Str::replace("\n", '', $process->getOutput());
                }

                $process = Ssh::create($system['user'], $system['hostname'])
                    ->usePassword($system['password'])
                    ->disableStrictHostKeyChecking()
                    ->execute("ip addr | grep \"inet \" | grep -v 'inet 127.0.0.1' | awk '{print $2}'");

                if($process->isSuccessful()) {
                    $result['ip_addresses'] = Str::of($process->getOutput())->explode("\n")->slice(0, -1)->toArray();
                }
            }

            // Find out how many updates do you have
            // Find out uptime
            if(collect(['Debian'])->contains($result['os_name'])) {
                $process = Ssh::create($system['user'], $system['hostname'])
                    ->usePassword($system['password'])
                    ->disableStrictHostKeyChecking()
                    ->execute('apt update > /dev/null 2>&1; apt list --upgradable 2>/dev/null | tail -n +2 | wc -l');

                if($process->isSuccessful()) {
                    $result['updates_available'] = Str::replace("\n", '', $process->getOutput());
                }
            }

            echo 'System "' . $result['hostname'] . '": OS found is ' . $result['os_name'] . ", updates avail.: " . $result['updates_available'] . ", uptime: " . $result['uptime'] . "\n";
            dd($result);
        }
    }
}
