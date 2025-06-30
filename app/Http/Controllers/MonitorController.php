<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function new() {
        return view('pages.monitors.new');
    }
}
