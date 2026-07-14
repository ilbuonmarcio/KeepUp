<?php

use App\Models\Monitor;
use App\Services\TelegramMonitorNotifier;
use Illuminate\Support\Facades\Http;

function telegramMonitor(array $attributes = []): Monitor
{
    return (new Monitor)->forceFill(array_merge([
        'name' => 'Production server',
        'hostname_ip' => 'server.example.com',
        'latest_check_positive' => 1,
        'updates_available' => 0,
        'threshold_updates_available' => 5,
        'uptime' => 10,
        'threshold_uptime' => 365,
    ], $attributes));
}

beforeEach(function () {
    config()->set('services.telegram.enabled', true);
    config()->set('services.telegram.bot_token', 'test-bot-token');
    config()->set('services.telegram.chat_id', '123456');
});

test('it sends a consolidated Telegram notification for triggered monitor thresholds', function () {
    Http::fake();

    $monitor = telegramMonitor([
        'updates_available' => 5,
        'uptime' => 365,
    ]);

    app(TelegramMonitorNotifier::class)->notify($monitor);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.telegram.org/bottest-bot-token/sendMessage'
            && $request['chat_id'] === '123456'
            && str_contains($request['text'], '🚨 Production server')
            && str_contains($request['text'], '📦 5 updates (limit: 5)')
            && str_contains($request['text'], '⏱️ 365d uptime (limit: 365d)');
    });
});

test('it sends a Telegram notification when a monitor is down', function () {
    Http::fake();

    app(TelegramMonitorNotifier::class)->notify(telegramMonitor([
        'latest_check_positive' => 0,
        'updates_available' => null,
        'uptime' => null,
    ]));

    Http::assertSent(fn ($request) => str_contains(
        $request['text'],
        '🔴 Down or unreachable',
    ));
});

test('it does not notify for a healthy monitor below its thresholds', function () {
    Http::fake();

    app(TelegramMonitorNotifier::class)->notify(telegramMonitor());

    Http::assertNothingSent();
});

test('it does not notify unless both Telegram settings are configured', function () {
    Http::fake();
    config()->set('services.telegram.chat_id');

    app(TelegramMonitorNotifier::class)->notify(telegramMonitor([
        'latest_check_positive' => 0,
    ]));

    Http::assertNothingSent();
});

test('it does not notify when Telegram is globally disabled', function () {
    Http::fake();
    config()->set('services.telegram.enabled', false);

    app(TelegramMonitorNotifier::class)->notify(telegramMonitor([
        'latest_check_positive' => 0,
    ]));

    Http::assertNothingSent();
});

test('Telegram delivery errors do not fail monitor processing', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response([], 500),
    ]);

    app(TelegramMonitorNotifier::class)->notify(telegramMonitor([
        'latest_check_positive' => 0,
    ]));

    Http::assertSentCount(1);
});
