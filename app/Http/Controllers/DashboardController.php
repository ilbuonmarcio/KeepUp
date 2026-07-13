<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\MonitorLastRefresh;

class DashboardController extends Controller
{
    public function index()
    {
        $monitors = Monitor::query()
            ->orderByRaw('LOWER(name) ASC')
            ->orderBy('name')
            ->get();

        return view('pages.dashboard.index')->with([
            'monitors' => $monitors,
            'last_refresh' => MonitorLastRefresh::latest()->first(),
            'stats' => [
                'healthy' => $monitors->where('latest_check_positive', 1)->count(),
                'unreachable' => $monitors->where('latest_check_positive', 0)->count(),
                'updates' => $monitors->sum('updates_available'),
            ],
        ]);
    }
}
