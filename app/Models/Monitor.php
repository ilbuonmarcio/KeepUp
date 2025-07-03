<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

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
}
