<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Monitor extends Model
{
    public $table = 'monitors';

    public $timestamps = true;

    protected ?string $temporarySshKey = null;

    protected static function booted(): void
    {
        static::updated(function (Monitor $monitor): void {
            if ($monitor->wasChanged('ssh_private_key')) {
                static::deleteSshKeyIfUnused($monitor->getOriginal('ssh_private_key'));
            }
        });

        static::deleted(function (Monitor $monitor): void {
            static::deleteSshKeyIfUnused($monitor->ssh_private_key);
        });
    }

    private static function deleteSshKeyIfUnused(?string $filename): void
    {
        if (blank($filename)) {
            return;
        }

        $isStillUsed = static::query()
            ->where('ssh_private_key', $filename)
            ->exists();

        if (! $isStillUsed) {
            Storage::disk('private_keys')->delete($filename);
        }
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    public function authMethod()
    {
        switch ($this->auth_method) {
            case 'password': return 'Password';
            case 'ssh_private_key': return 'SSH Private Key';
            default: return '-';
        }
    }

    public function ipAddresses()
    {
        if (is_null($this->ip_addresses)) {
            return '-';
        } else {
            $str = '';
            $ips = collect(json_decode($this->ip_addresses, JSON_OBJECT_AS_ARRAY));
            foreach ($ips as $ip) {
                $isPublicIp = filter_var(
                    explode('/', $ip)[0],
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                ) !== false;

                $str .= '<div class="'.($isPublicIp ? 'is-public-ip' : '').'" '.($isPublicIp ? 'title="Public IP"' : '').'>'.$ip.'</div>';
            }

            return $str;
        }
    }

    public function hasPublicIp(): bool
    {
        $addresses = json_decode($this->ip_addresses ?? '[]', true);

        if (! is_array($addresses)) {
            return false;
        }

        return collect($addresses)->contains(fn ($address) => filter_var(
            explode('/', $address)[0],
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false);
    }

    public function firewallIsActive(): bool
    {
        $rules = json_decode($this->firewall_rules ?? '[]', true);

        if (! is_array($rules)) {
            return false;
        }

        return collect($rules)->contains(fn ($rule) => trim($rule) === 'Status: active');
    }

    public function firewallRules()
    {
        if (is_null($this->firewall_rules)) {
            return '<span class="notify-label"><i class="fas fa-warning"></i> UFW Not Installed</span>';
        } else {
            $str = '';
            $rows = collect(json_decode($this->firewall_rules, JSON_OBJECT_AS_ARRAY));

            if (count($rows) == 0) {
                return '<span class="notify-label"><i class="fas fa-warning"></i> UFW Not Installed</span>';
            }
            if ($rows->first() == 'Status: inactive') {
                return '<span class="warning-label"><i class="fas fa-warning"></i> UFW inactive</span>';
            }

            foreach ($rows as $row) {
                if (strlen($row) == 0) {
                    $str .= '<br>';
                }
                $str .= '<pre>'.$row.'</pre>';
            }

            return $str;
        }
    }

    public function status()
    {
        if ($this->latest_check_positive) {
            return '<div style="margin-top: 12px; !important; font-size: 12px;">Last good check:<br>'.Carbon::parse($this->latest_successful_check)->format('Y-m-d H:i').'</div>';
        } else {
            return '<div style="margin-top: 12px; !important; font-size: 12px;">Last good check:<br>'.Carbon::parse($this->latest_successful_check)->format('Y-m-d H:i').'</div>';
        }
    }

    public function thresholdUptimeTriggered()
    {
        return $this->uptime >= $this->threshold_uptime;
    }

    public function thresholdUpdatesAvailableTriggered()
    {
        return $this->updates_available >= $this->threshold_updates_available;
    }

    public function markCheckSuccessful(?Carbon $checkedAt = null): void
    {
        $this->latest_check_positive = 1;
        $this->latest_successful_check = $checkedAt ?? Carbon::now();
    }

    public function markCheckFailed(): void
    {
        $this->latest_check_positive = 0;
    }

    public function version()
    {
        $version = new MonitorVersion;
        $version->monitor_id = $this->id;
        $version->uptime = $this->uptime;
        $version->updates_available = $this->updates_available;
        $version->check_time = $this->check_time;
        $version->save();
    }

    public function sshKeyDecrypt(): string
    {
        $this->sshKeyDecryptFlush();

        $encrypted = Storage::disk('private_keys')->get($this->ssh_private_key);
        $decrypted = Crypt::decryptString($encrypted);

        $this->temporarySshKey = '.keepup-'.Str::random(40).'.key';
        Storage::disk('private_keys')->put($this->temporarySshKey, $decrypted);
        $path = Storage::disk('private_keys')->path($this->temporarySshKey);
        chmod($path, 0600);

        return $path;
    }

    public function sshKeyDecryptFlush(): void
    {
        if ($this->temporarySshKey === null) {
            return;
        }

        Storage::disk('private_keys')->delete($this->temporarySshKey);
        $this->temporarySshKey = null;
    }

    public function asIcon()
    {
        switch ($this->operating_system) {
            case 'Debian':
                return '<i class="fa-brands fa-debian color-debian"></i>';

            case 'Ubuntu':
                return '<i class="fa-brands fa-ubuntu color-ubuntu"></i>';

            case 'Arch Linux':
                return '<img src="/images/os-icons/archlinux.svg"/ style="height:1em; vertical-align:-0.1em;">';

            case 'Proxmox VE':
                return '<img src="/images/os-icons/proxmox.svg"/ style="height:1em; vertical-align:-0.1em;">';

            default:
                return '';

        }
    }
}
