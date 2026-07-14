<?php

namespace App\Services;

use App\Models\Monitor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramMonitorNotifier
{
    public function notify(Monitor $monitor): void
    {
        if (! config('services.telegram.enabled')) {
            return;
        }

        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (blank($botToken) || blank($chatId)) {
            return;
        }

        $issues = $this->issues($monitor);

        if ($issues === []) {
            return;
        }

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $this->message($monitor, $issues),
                ],
            );

            if ($response->failed()) {
                Log::warning('Telegram monitor notification failed.', [
                    'monitor_id' => $monitor->getKey(),
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Telegram monitor notification could not be sent.', [
                'monitor_id' => $monitor->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function issues(Monitor $monitor): array
    {
        $issues = [];

        if (! $monitor->latest_check_positive) {
            $issues[] = '🔴 Down or unreachable';
        }

        if ($monitor->updates_available !== null && $monitor->thresholdUpdatesAvailableTriggered()) {
            $issues[] = sprintf(
                '📦 %s updates (limit: %s)',
                $monitor->updates_available,
                $monitor->threshold_updates_available,
            );
        }

        if ($monitor->uptime !== null && $monitor->thresholdUptimeTriggered()) {
            $issues[] = sprintf(
                '⏱️ %sd uptime (limit: %sd)',
                $monitor->uptime,
                $monitor->threshold_uptime,
            );
        }

        return $issues;
    }

    /**
     * @param  array<int, string>  $issues
     */
    private function message(Monitor $monitor, array $issues): string
    {
        $lines = [
            '🚨 '.$monitor->name,
            '🖥️ '.$monitor->hostname_ip,
            ...$issues,
            '🕒 '.now()->format('Y-m-d H:i'),
        ];

        return implode("\n", $lines);
    }
}
