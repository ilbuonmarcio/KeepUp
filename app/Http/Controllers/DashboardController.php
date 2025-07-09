<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\MonitorLastRefresh;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index() {
        return view('pages.dashboard.index')->with([
            'monitors' => Monitor::get(),
            'last_refresh' => MonitorLastRefresh::latest()->first()
        ]);
    }
}
