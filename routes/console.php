<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:monitor')->dailyAt('08:00');
Schedule::command('app:send-email-monitor-recap')->weekly();