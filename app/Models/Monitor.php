<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

class Monitor extends Model
{
    public $table = 'monitors';
    public $timestamps = true;

    public function authMethod() {
        switch($this->auth_method) {
            case 'password': return 'Password';
            case 'ssh_private_key': return 'SSH Private Key';
            default: return '-';
        }
    }

    public function ipAddresses() {
        if(is_null($this->ip_addresses)) {
            return '-';
        } else {
            $ips = collect(json_decode($this->ip_addresses, JSON_OBJECT_AS_ARRAY));
            return $ips->join('<br>');
        }
    }

    public function status() {
        if($this->latest_check_positive) {
            return '<span class="monitor-status-good">Good</span><br><small>Last good check:<br>' . Carbon::parse($this->latest_successful_check)->format('Y-m-d H:i') . '</small>';
        } else {
            return '<span class="monitor-status-bad">Bad</span><br><small>Last good check:<br>' . Carbon::parse($this->latest_successful_check)->format('Y-m-d H:i') . '</small>';
        }
    }

    public function sshPrivateKeyFullPath() {
        return storage_path('app/private/ssh_private_keys/' . $this->ssh_private_key);
    }

    public function thresholdUptimeTriggered() {
        return $this->uptime >= $this->threshold_uptime;
    }

    public function thresholdUpdatesAvailableTriggered() {
        return $this->updates_available >= $this->threshold_updates_available;
    }

    public function version() {
        $version = new MonitorVersion();
        $version->monitor_id = $this->id;
        $version->uptime = $this->uptime;
        $version->updates_available = $this->updates_available;
        $version->check_time = $this->check_time;
        $version->save();
    }

    public function sshKeyDecrypt() {
        $this->sshKeyDecryptFlush();

        $encrypted = file_get_contents($this->sshPrivateKeyFullPath());
        $decrypted = Crypt::decryptString($encrypted);

        // Save temporarily
        Storage::disk('private_keys')->put($this->ssh_private_key . '.decrypt', $decrypted);
        chmod(storage_path('app/private/ssh_private_keys/' . $this->ssh_private_key . '.decrypt'), 0600);
        chown(storage_path('app/private/ssh_private_keys/' . $this->ssh_private_key . '.decrypt'), 'www-data');
    }

    public function sshKeyDecryptFlush() {
        Storage::disk('private_keys')->delete($this->ssh_private_key . '.decrypt');
    }

    public function asIcon() {
        switch($this->operating_system) {
            case 'Debian': {
                return '<i class="fa-brands fa-debian color-debian"></i>';
            }
            case 'Ubuntu': {
                return '<i class="fa-brands fa-ubuntu color-ubuntu"></i>';
            }
            case 'Arch Linux': {
                return '<img src="/images/os-icons/archlinux.svg"/ style="height:1em; vertical-align:-0.1em;">';
            }
            default: {
                return '';
            }
        }
    }
}
