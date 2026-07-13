<?php

use App\Jobs\RunMonitorOnDemand;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

function editableMonitor(string $authMethod = 'password'): Monitor
{
    $monitor = new Monitor;
    $monitor->name = 'Existing monitor';
    $monitor->hostname_ip = '192.0.2.20';
    $monitor->username = 'deploy';
    $monitor->auth_method = $authMethod;
    $monitor->threshold_uptime = 365;
    $monitor->threshold_updates_available = 1;

    if ($authMethod === 'password') {
        $monitor->password = Crypt::encryptString('current-password');
    } else {
        $monitor->ssh_private_key = 'existing-key.key';
    }

    $monitor->save();

    return $monitor;
}

function editableMonitorPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Updated monitor',
        'hostname_ip' => 'server.example.com',
        'username' => 'keepup',
        'auth_method' => 'password',
        'threshold_uptime' => 180,
        'threshold_updates_available' => 5,
    ], $overrides);
}

test('the edit form is prefilled without exposing credentials', function () {
    $monitor = editableMonitor();
    $encryptedPassword = $monitor->password;

    $this->actingAs(User::factory()->create())
        ->get(route('monitors.edit', $monitor))
        ->assertOk()
        ->assertSee('Edit monitor')
        ->assertSee('Existing monitor')
        ->assertSee('Leave blank to keep the current password.')
        ->assertDontSee($encryptedPassword);
});

test('a monitor can be updated while retaining its current password', function () {
    Bus::fake();

    $monitor = editableMonitor();
    $encryptedPassword = $monitor->password;

    $this->actingAs(User::factory()->create())
        ->put(route('monitors.update', $monitor), editableMonitorPayload())
        ->assertOk()
        ->assertJson(['status' => true]);

    $monitor->refresh();

    expect($monitor->name)->toBe('Updated monitor')
        ->and($monitor->hostname_ip)->toBe('server.example.com')
        ->and($monitor->username)->toBe('keepup')
        ->and($monitor->threshold_uptime)->toEqual(180)
        ->and($monitor->threshold_updates_available)->toBe(5)
        ->and($monitor->password)->toBe($encryptedPassword);

    Bus::assertDispatched(
        RunMonitorOnDemand::class,
        fn (RunMonitorOnDemand $job) => $job->monitorId === $monitor->getKey(),
    );
});

test('an ssh private key can be retained while editing a monitor', function () {
    Storage::fake('private_keys');
    Bus::fake();

    Storage::disk('private_keys')->put('existing-key.key', Crypt::encryptString('private-key'));
    $monitor = editableMonitor('ssh_private_key');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertOk()
        ->assertSee('Keep current private key')
        ->assertDontSee('existing-key.key');

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), editableMonitorPayload([
            'auth_method' => 'ssh_private_key',
        ]))
        ->assertOk();

    expect($monitor->fresh()->ssh_private_key)->toBe('existing-key.key');
});

test('switching authentication methods requires replacement credentials', function () {
    Storage::fake('private_keys');
    Bus::fake();

    $monitor = editableMonitor();

    $this->actingAs(User::factory()->create())
        ->put(route('monitors.update', $monitor), editableMonitorPayload([
            'auth_method' => 'ssh_private_key',
        ]))
        ->assertInvalid('ssh_private_key');

    expect($monitor->fresh()->auth_method)->toBe('password');
    Bus::assertNothingDispatched();
});

test('a monitor can switch from an ssh key to a password', function () {
    Storage::fake('private_keys');
    Bus::fake();

    Storage::disk('private_keys')->put('existing-key.key', Crypt::encryptString('private-key'));
    $monitor = editableMonitor('ssh_private_key');

    $this->actingAs(User::factory()->create())
        ->put(route('monitors.update', $monitor), editableMonitorPayload([
            'password' => 'replacement-password',
        ]))
        ->assertOk();

    $monitor->refresh();

    expect($monitor->auth_method)->toBe('password')
        ->and(Crypt::decryptString($monitor->password))->toBe('replacement-password')
        ->and($monitor->ssh_private_key)->toBeNull();
});

test('guests cannot edit monitors', function () {
    $monitor = editableMonitor();

    $this->get(route('monitors.edit', $monitor))
        ->assertRedirect(route('login'));

    $this->put(route('monitors.update', $monitor), editableMonitorPayload())
        ->assertRedirect(route('login'));
});
