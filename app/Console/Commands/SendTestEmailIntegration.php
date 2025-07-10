<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Mail\TestEmailIntegration;
use Illuminate\Support\Facades\Mail;

class SendTestEmailIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-test-email-integration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email using Google Workspace SMTP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Mail::to(env('MAIL_TO_ADDRESS_INTEGRATION_TEST', 'demo@example.com'))->send(new TestEmailIntegration());
        $this->info('Email sent!');
    }
}
