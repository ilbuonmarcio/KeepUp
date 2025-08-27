<?php

namespace App\Http\Controllers;

use App\Jobs\RunMonitorOnDemand;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

class MonitorController extends Controller
{
    public function new() {
        return view('pages.monitors.new');
    }

    public function create(Request $request) {
        $validated = $request->validate([
            'name' => 'string|required',
            'hostname_ip' => 'string|required',
            'username' => 'string|required',
            'auth_method' => 'string|required',
            'password' => 'string|nullable',
            'ssh_private_key' => 'required',
            'threshold_uptime' => 'numeric|required',
            'threshold_updates_available' => 'numeric|required'
        ]);

        if(!in_array($validated['auth_method'], ['password', 'ssh_private_key'])) {
            return abort(422);
        }
        if($validated['auth_method'] == 'password' && is_null($validated['password'])) {
            return abort(422);
        }
        if($validated['auth_method'] == 'ssh_private_key' && is_null($validated['ssh_private_key'])) {
            return abort(422);
        }

        $monitor = new Monitor();
        $monitor->name = $validated['name'];
        $monitor->hostname_ip = $validated['hostname_ip'];
        $monitor->username = $validated['username'];
        $monitor->auth_method = $validated['auth_method'];
        $monitor->threshold_uptime = $validated['threshold_uptime'];
        $monitor->threshold_updates_available = $validated['threshold_updates_available'];
        if($validated['auth_method'] == 'password') {
            $monitor->password = Crypt::encryptString($validated['password']);
        } else {
            // Save to local storage the content as a file, and save the reference to it to database
            $key_filename = Str::random(40) . '.key';

            $directory = storage_path('app/private/ssh_private_keys');
            if (!is_dir($directory)) {
                mkdir($directory, 0770, true);  // Creates the directory with correct permissions, if it's not already available
            }

            // Save to disk and change permissions for proper usage
            $file = $request->file('ssh_private_key');
            $encryptedContent = Crypt::encryptString($file->getContent());
            Storage::disk('private_keys')->put($key_filename, $encryptedContent);
            chmod(storage_path('app/private/ssh_private_keys/' . $key_filename), 0770);

            $monitor->ssh_private_key = $key_filename;
        }
        $monitor->save();

        dispatch(new RunMonitorOnDemand());

        return array('status' => true);
    }

    public function delete(Request $request) {
        $validated = $request->validate([
            'id_monitor' => 'integer|required'
        ]);

        // TODO: remove ssh key file before deleting!

        $monitor = Monitor::findOrFail($validated['id_monitor']);
        $monitor->delete();

        return array('status' => true);
    }

    public function runMonitorsOnDemand() {
        dispatch(new RunMonitorOnDemand());

        return redirect('/');
    }
}
