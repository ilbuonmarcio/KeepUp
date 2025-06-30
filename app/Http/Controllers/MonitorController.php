<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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
            'ssh_private_key' => 'string|nullable'
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
        $monitor->password = $validated['password'];
        $monitor->ssh_private_key = $validated['ssh_private_key'];
        $monitor->save();

        return array('status' => true);
    }
}
