<?php

namespace App\Services;

use JsonException;

class WindowsPowerShellCollector
{
    public function command(): string
    {
        $encoded = base64_encode(mb_convert_encoding($this->script(), 'UTF-16LE', 'UTF-8'));

        return "powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand {$encoded}";
    }

    public function parse(string $output): ?array
    {
        $data = $this->decodePayload($output);

        if ($data === null) {
            return null;
        }

        if (! is_array($data) || ($data['operating_system'] ?? null) !== 'Windows') {
            return null;
        }

        foreach (['ip_addresses', 'firewall_rules'] as $field) {
            if (isset($data[$field]) && ! is_array($data[$field])) {
                $data[$field] = [$data[$field]];
            }
        }

        return [
            'operating_system' => 'Windows',
            'operating_system_full_version' => $data['operating_system_full_version'] ?? 'Windows',
            'updates_available' => isset($data['updates_available']) ? (int) $data['updates_available'] : null,
            'uptime' => isset($data['uptime']) ? (float) $data['uptime'] : null,
            'ip_addresses' => $data['ip_addresses'] ?? null,
            'cpu_load' => isset($data['cpu_load']) ? (string) $data['cpu_load'] : null,
            'disks_status' => $data['disks_status'] ?? null,
            'docker_daemon_running' => isset($data['docker_daemon_running']) ? (int) $data['docker_daemon_running'] : null,
            'docker_active_containers' => isset($data['docker_active_containers']) ? (int) $data['docker_active_containers'] : null,
            'firewall_rules' => $data['firewall_rules'] ?? null,
        ];
    }

    private function decodePayload(string $output): ?array
    {
        $lines = array_reverse(preg_split('/\R/', trim($output)) ?: []);

        foreach ($lines as $line) {
            try {
                $data = json_decode(trim($line), true, 16, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (is_array($data) && ($data['operating_system'] ?? null) === 'Windows') {
                return $data;
            }
        }

        return null;
    }

    private function script(): string
    {
        return <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$os = Get-CimInstance -ClassName Win32_OperatingSystem
$result = [ordered]@{
    operating_system = 'Windows'
    operating_system_full_version = ('{0} {1} (Build {2})' -f $os.Caption, $os.Version, $os.BuildNumber)
    uptime = [math]::Round(((Get-Date) - $os.LastBootUpTime).TotalDays, 2)
    updates_available = $null
    ip_addresses = $null
    cpu_load = $null
    disks_status = $null
    docker_daemon_running = 0
    docker_active_containers = $null
    firewall_rules = $null
}

try {
    $result.ip_addresses = @(Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object { $_.IPAddress -ne '127.0.0.1' -and $_.AddressState -eq 'Preferred' } |
        ForEach-Object { '{0}/{1}' -f $_.IPAddress, $_.PrefixLength })
} catch {}

try {
    $cpu = Get-CimInstance -ClassName Win32_Processor | Measure-Object -Property LoadPercentage -Average
    if ($null -ne $cpu.Average) { $result.cpu_load = [math]::Round($cpu.Average, 1) }
} catch {}

try {
    $diskLines = @('Drive  Size      Used      Available  Use%')
    Get-CimInstance -ClassName Win32_LogicalDisk -Filter 'DriveType=3' | ForEach-Object {
        $used = $_.Size - $_.FreeSpace
        $percent = if ($_.Size -gt 0) { [math]::Round(($used / $_.Size) * 100) } else { 0 }
        $diskLines += ('{0,-6} {1,7:N1} GB {2,7:N1} GB {3,9:N1} GB {4,3}%' -f $_.DeviceID, ($_.Size / 1GB), ($used / 1GB), ($_.FreeSpace / 1GB), $percent)
    }
    $result.disks_status = $diskLines -join "`n"
} catch {}

try {
    $profiles = @(Get-NetFirewallProfile)
    $enabled = @($profiles | Where-Object Enabled).Count -gt 0
    $result.firewall_rules = @($(if ($enabled) { 'Status: active' } else { 'Status: inactive' }))
    $result.firewall_rules += $profiles | ForEach-Object { '{0}: {1}' -f $_.Name, $(if ($_.Enabled) { 'enabled' } else { 'disabled' }) }
} catch {}

try {
    & docker.exe info *> $null
    if ($LASTEXITCODE -eq 0) {
        $result.docker_daemon_running = 1
        $result.docker_active_containers = @(& docker.exe ps -q).Count
    }
} catch {}

try {
    $updateJob = Start-Job -ScriptBlock {
        $session = New-Object -ComObject Microsoft.Update.Session
        $searcher = $session.CreateUpdateSearcher()
        $searcher.Search("IsInstalled=0 and IsHidden=0").Updates.Count
    }
    if (Wait-Job -Job $updateJob -Timeout 30) {
        $result.updates_available = [int](Receive-Job -Job $updateJob)
    }
    Remove-Job -Job $updateJob -Force
} catch {}

$result | ConvertTo-Json -Compress -Depth 4
POWERSHELL;
    }
}
