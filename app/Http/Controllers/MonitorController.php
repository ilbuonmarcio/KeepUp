<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function new() {
        return view('pages.monitors.new');
    }

    public function create(Request $request) {
        return array('status' => true);
    }
}
