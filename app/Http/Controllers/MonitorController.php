<?php

namespace App\Http\Controllers;

use App\Jobs\RunMonitorOnDemand;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonitorController extends Controller
{
    public function new()
    {
        $sshKeySources = Monitor::query()
            ->where('auth_method', 'ssh_private_key')
            ->whereNotNull('ssh_private_key')
            ->get(['id', 'name', 'hostname_ip', 'username', 'ssh_private_key'])
            ->unique('ssh_private_key')
            ->filter(fn (Monitor $monitor) => Storage::disk('private_keys')->exists($monitor->ssh_private_key));

        return view('pages.monitors.new', compact('sshKeySources'));
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hostname_ip' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'auth_method' => ['required', Rule::in(['password', 'ssh_private_key'])],
            'password' => ['nullable', 'string'],
            'ssh_private_key' => ['nullable', 'file', 'max:64'],
            'existing_ssh_key_monitor_id' => ['nullable', 'integer'],
            'threshold_uptime' => ['required', 'numeric', 'min:0'],
            'threshold_updates_available' => ['required', 'integer', 'min:1'],
        ]);

        if ($validated['auth_method'] === 'password' && blank($validated['password'])) {
            throw ValidationException::withMessages(['password' => 'A password is required.']);
        }

        $monitor = new Monitor;
        $monitor->name = $validated['name'];
        $monitor->hostname_ip = $validated['hostname_ip'];
        $monitor->username = $validated['username'];
        $monitor->auth_method = $validated['auth_method'];
        $monitor->threshold_uptime = $validated['threshold_uptime'];
        $monitor->threshold_updates_available = $validated['threshold_updates_available'];
        if ($validated['auth_method'] === 'password') {
            $monitor->password = Crypt::encryptString($validated['password']);
        } else {
            $monitor->ssh_private_key = $this->resolveSshPrivateKey($request, $validated);
        }
        $monitor->save();

        dispatch(new RunMonitorOnDemand);

        return ['status' => true];
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'id_monitor' => 'integer|required',
        ]);

        // TODO: remove ssh key file before deleting!

        $monitor = Monitor::findOrFail($validated['id_monitor']);
        $monitor->delete();

        return ['status' => true];
    }

    public function runMonitorsOnDemand()
    {
        dispatch(new RunMonitorOnDemand);

        return redirect('/');
    }

    public function refresh(Monitor $monitor): JsonResponse
    {
        dispatch(new RunMonitorOnDemand($monitor->getKey()));

        return response()->json(['status' => true], 202);
    }

    private function resolveSshPrivateKey(Request $request, array $validated): string
    {
        $uploadedKey = $request->file('ssh_private_key');
        $sourceMonitorId = $validated['existing_ssh_key_monitor_id'] ?? null;

        if ($uploadedKey && $sourceMonitorId) {
            throw ValidationException::withMessages([
                'ssh_private_key' => 'Upload a new key or select an existing key, not both.',
            ]);
        }

        if ($sourceMonitorId) {
            $sourceMonitor = Monitor::query()
                ->where('auth_method', 'ssh_private_key')
                ->whereNotNull('ssh_private_key')
                ->find($sourceMonitorId);

            if (! $sourceMonitor || ! Storage::disk('private_keys')->exists($sourceMonitor->ssh_private_key)) {
                throw ValidationException::withMessages([
                    'existing_ssh_key_monitor_id' => 'The selected SSH private key is unavailable.',
                ]);
            }

            chmod(Storage::disk('private_keys')->path($sourceMonitor->ssh_private_key), 0600);

            return $sourceMonitor->ssh_private_key;
        }

        if (! $uploadedKey) {
            throw ValidationException::withMessages([
                'ssh_private_key' => 'Upload a private key or select an existing one.',
            ]);
        }

        $privateKey = $uploadedKey->getContent();
        if (! preg_match('/-----BEGIN (?:OPENSSH |RSA |EC |DSA )?PRIVATE KEY-----/', $privateKey)) {
            throw ValidationException::withMessages([
                'ssh_private_key' => 'The uploaded file is not a supported SSH private key.',
            ]);
        }

        $directory = Storage::disk('private_keys')->path('');
        File::ensureDirectoryExists($directory, 0700);
        chmod($directory, 0700);

        $keyFilename = Str::random(40).'.key';
        Storage::disk('private_keys')->put($keyFilename, Crypt::encryptString($privateKey));
        chmod(Storage::disk('private_keys')->path($keyFilename), 0600);

        return $keyFilename;
    }
}
