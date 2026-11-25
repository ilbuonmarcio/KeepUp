<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:monitor --concurrency=5')->dailyAt('08:00');
