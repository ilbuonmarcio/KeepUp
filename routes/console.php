<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:monitor')->dailyAt('08:00');