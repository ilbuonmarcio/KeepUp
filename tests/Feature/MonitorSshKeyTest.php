<?php

use App\Jobs\RunMonitorOnDemand;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

function monitorPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Keyed server',
        'hostname_ip' => '192.0.2.10',
        'username' => 'keepup',
        'auth_method' => 'ssh_private_key',
        'threshold_uptime' => 365,
        'threshold_updates_available' => 1,
    ], $overrides);
}

function storedKeyMonitor(string $filename = 'stored-key.key'): Monitor
{
    $monitor = new Monitor;
    $monitor->name = 'Existing server';
    $monitor->hostname_ip = '192.0.2.20';
    $monitor->username = 'deploy';
    $monitor->auth_method = 'ssh_private_key';
    $monitor->ssh_private_key = $filename;
    $monitor->save();

    return $monitor;
}

test('uploaded ssh private keys are encrypted at rest with private permissions', function () {
    Storage::fake('private_keys');
    Bus::fake();

    $privateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\ntest-private-material\n-----END OPENSSH PRIVATE KEY-----\n";
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('monitors.create'), monitorPayload([
            'ssh_private_key' => UploadedFile::fake()->createWithContent('id_ed25519', $privateKey),
        ]))
        ->assertOk()
        ->assertJson(['status' => true]);

    $monitor = Monitor::query()->where('name', 'Keyed server')->firstOrFail();
    $encryptedKey = Storage::disk('private_keys')->get($monitor->ssh_private_key);

    expect($encryptedKey)->not->toContain('test-private-material')
        ->and(Crypt::decryptString($encryptedKey))->toBe($privateKey)
        ->and(fileperms(Storage::disk('private_keys')->path($monitor->ssh_private_key)) & 0777)->toBe(0600);

    Bus::assertDispatched(RunMonitorOnDemand::class);
});

test('an existing encrypted ssh key can be reused without exposing its filename', function () {
    Storage::fake('private_keys');
    Bus::fake();

    Storage::disk('private_keys')->put('stored-key.key', Crypt::encryptString('existing-private-key'));
    $sourceMonitor = storedKeyMonitor();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('monitors.new'))
        ->assertOk()
        ->assertSee('Reuse key from Existing server')
        ->assertDontSee('stored-key.key');

    $this->actingAs($user)
        ->post(route('monitors.create'), monitorPayload([
            'name' => 'Reusing server',
            'existing_ssh_key_monitor_id' => $sourceMonitor->getKey(),
        ]))
        ->assertOk();

    $monitor = Monitor::query()->where('name', 'Reusing server')->firstOrFail();

    expect($monitor->ssh_private_key)->toBe('stored-key.key')
        ->and(Storage::disk('private_keys')->allFiles())->toHaveCount(1);
});

test('a public key cannot be uploaded as a private key', function () {
    Storage::fake('private_keys');
    Bus::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('monitors.create'), monitorPayload([
            'ssh_private_key' => UploadedFile::fake()->createWithContent('id_ed25519.pub', 'ssh-ed25519 AAAA-test'),
        ]))
        ->assertInvalid('ssh_private_key');

    expect(Monitor::query()->count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('decrypted ssh key files are unique and removed after use', function () {
    Storage::fake('private_keys');

    $privateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\ntemporary-material\n-----END OPENSSH PRIVATE KEY-----\n";
    Storage::disk('private_keys')->put('stored-key.key', Crypt::encryptString($privateKey));
    $monitor = storedKeyMonitor();

    $temporaryPath = $monitor->sshKeyDecrypt();

    expect(file_get_contents($temporaryPath))->toBe($privateKey)
        ->and(fileperms($temporaryPath) & 0777)->toBe(0600);

    $monitor->sshKeyDecryptFlush();

    expect(file_exists($temporaryPath))->toBeFalse();
});

test('deleting the last monitor using an ssh key removes the encrypted key file', function () {
    Storage::fake('private_keys');

    Storage::disk('private_keys')->put('stored-key.key', Crypt::encryptString('existing-private-key'));
    $monitor = storedKeyMonitor();

    $this->actingAs(User::factory()->create())
        ->post(route('monitors.delete'), ['id_monitor' => $monitor->getKey()])
        ->assertOk()
        ->assertJson(['status' => true]);

    Storage::disk('private_keys')->assertMissing('stored-key.key');
});

test('deleting a monitor preserves an ssh key that another monitor still uses', function () {
    Storage::fake('private_keys');

    Storage::disk('private_keys')->put('shared-key.key', Crypt::encryptString('shared-private-key'));
    $firstMonitor = storedKeyMonitor('shared-key.key');
    $secondMonitor = $firstMonitor->replicate();
    $secondMonitor->name = 'Second server';
    $secondMonitor->save();

    $firstMonitor->delete();

    Storage::disk('private_keys')->assertExists('shared-key.key');

    $secondMonitor->delete();

    Storage::disk('private_keys')->assertMissing('shared-key.key');
});

test('changing away from an ssh key removes it when no monitor still uses it', function () {
    Storage::fake('private_keys');

    Storage::disk('private_keys')->put('old-key.key', Crypt::encryptString('old-private-key'));
    $monitor = storedKeyMonitor('old-key.key');

    $monitor->ssh_private_key = null;
    $monitor->auth_method = 'password';
    $monitor->password = Crypt::encryptString('replacement-password');
    $monitor->save();

    Storage::disk('private_keys')->assertMissing('old-key.key');
});
