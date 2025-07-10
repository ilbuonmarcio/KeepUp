<?php

namespace App\Console\Commands;

use App\Mail\SendMonitorsRecap;
use Illuminate\Console\Command;
use App\Mail\TestEmailIntegration;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Exception;
use Illuminate\Support\Facades\Log;

class SendEmailMonitorsRecap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-email-monitor-recap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an email with Monitors status recap';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Send to each user registered
        $monitors = Monitor::get();
        foreach(User::get() as $user) {
            try {
                Mail::to($user->email)->send(new SendMonitorsRecap($monitors));
                Log::channel('monitors_stacked')->info("Email recap sent to $user->email!");
            } catch (Exception $e) {
                Log::channel('monitors_stacked')->error("Email recap error while sending to $user->email!");
            }
        }
    }
}
