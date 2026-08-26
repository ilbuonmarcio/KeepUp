<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WindowsDomain extends Model
{
    protected ?string $temporarySshKey = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discovery_password' => 'encrypted',
            'monitor_password' => 'encrypted',
            'last_discovered_at' => 'datetime',
            'threshold_uptime' => 'float',
            'threshold_updates_available' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (WindowsDomain $domain): void {
            static::deleteSshKeyIfUnused($domain->ssh_private_key);
        });
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    public static function deleteSshKeyIfUnused(?string $filename): void
    {
        if (blank($filename)) {
            return;
        }

        $usedByDomain = static::query()->where('ssh_private_key', $filename)->exists();
        $usedByMonitor = Monitor::query()->where('ssh_private_key', $filename)->exists();

        if (! $usedByDomain && ! $usedByMonitor) {
            Storage::disk('private_keys')->delete($filename);
        }
    }

    public function sshKeyDecrypt(): string
    {
        $this->sshKeyDecryptFlush();

        $encrypted = Storage::disk('private_keys')->get($this->ssh_private_key);
        $decrypted = Crypt::decryptString($encrypted);

        $this->temporarySshKey = '.keepup-domain-'.Str::random(40).'.key';
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
}
