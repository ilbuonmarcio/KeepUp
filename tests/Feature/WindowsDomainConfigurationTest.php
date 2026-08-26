<?php

use App\Models\Monitor;
use App\Models\User;
use App\Models\WindowsDomain;
use App\Services\WindowsDomainDiscoveryService;
use App\Services\WindowsDomainSynchronizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

function configuredWindowsDomain(array $overrides = []): WindowsDomain
{
    return WindowsDomain::query()->create(array_merge([
        'name' => 'Corporate domain',
        'domain_name' => 'example.local',
        'ldap_host' => 'dc01.example.local',
        'ldap_port' => 636,
        'ldap_security' => 'ldaps',
        'base_dn' => 'DC=example,DC=local',
        'discovery_username' => 'discovery@example.local',
        'discovery_password' => 'discovery-secret',
        'monitor_username' => 'monitor@example.local',
        'auth_method' => 'password',
        'monitor_password' => 'monitor-secret',
        'threshold_uptime' => 365,
        'threshold_updates_available' => 1,
    ], $overrides));
}

test('domain synchronization reconciles computers and inherited credentials', function () {
    $domain = configuredWindowsDomain();
    $discovery = Mockery::mock(WindowsDomainDiscoveryService::class);
    $discovery->shouldReceive('discover')->once()->andReturn([
        ['name' => 'WORKSTATION-01', 'hostname' => 'workstation-01.example.local', 'operating_system' => 'Windows 11'],
        ['name' => 'SERVER-01', 'hostname' => 'server-01.example.local', 'operating_system' => 'Windows Server 2025'],
    ]);

    expect((new WindowsDomainSynchronizer($discovery))->sync($domain))->toBe(2);

    $monitor = Monitor::query()->where('hostname_ip', 'workstation-01.example.local')->firstOrFail();
    expect($monitor->windows_domain_id)->toBe($domain->id)
        ->and($monitor->username)->toBe('monitor@example.local')
        ->and($monitor->auth_method)->toBe('password')
        ->and(Crypt::decryptString($monitor->password))->toBe('monitor-secret');

    $secondDiscovery = Mockery::mock(WindowsDomainDiscoveryService::class);
    $secondDiscovery->shouldReceive('discover')->once()->andReturn([
        ['name' => 'WORKSTATION-01', 'hostname' => 'workstation-01.example.local', 'operating_system' => 'Windows 11'],
    ]);
    Monitor::query()->whereKey($monitor->id)->update(['updated_at' => now()->subDay()]);
    $unchangedTimestamp = $monitor->fresh()->updated_at;
    (new WindowsDomainSynchronizer($secondDiscovery))->sync($domain->fresh());

    expect($domain->monitors()->count())->toBe(1)
        ->and(Monitor::query()->where('hostname_ip', 'server-01.example.local')->exists())->toBeFalse()
        ->and($monitor->fresh()->updated_at->equalTo($unchangedTimestamp))->toBeTrue();
});

test('failed discovery preserves existing domain monitors', function () {
    $domain = configuredWindowsDomain();
    $monitor = new Monitor;
    $monitor->windows_domain_id = $domain->id;
    $monitor->name = 'Existing workstation';
    $monitor->hostname_ip = 'existing.example.local';
    $monitor->username = $domain->monitor_username;
    $monitor->auth_method = 'password';
    $monitor->password = Crypt::encryptString('monitor-secret');
    $monitor->save();

    $discovery = Mockery::mock(WindowsDomainDiscoveryService::class);
    $discovery->shouldReceive('discover')->once()->andThrow(new RuntimeException('Directory unavailable'));

    expect(fn () => (new WindowsDomainSynchronizer($discovery))->sync($domain))->toThrow(RuntimeException::class)
        ->and($monitor->fresh())->not->toBeNull()
        ->and($domain->fresh()->last_error)->toBe('Directory unavailable');
});

test('configuration stores encrypted credentials and an encrypted private key', function () {
    Storage::fake('private_keys');
    $this->mock(WindowsDomainSynchronizer::class)
        ->shouldReceive('sync')
        ->once()
        ->andReturn(0);

    $privateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\ndomain-private-material\n-----END OPENSSH PRIVATE KEY-----\n";

    $this->actingAs(User::factory()->create())
        ->post(route('configuration.domains.store'), [
            'name' => 'Example domain',
            'domain_name' => 'example.local',
            'ldap_host' => 'dc01.example.local',
            'ldap_port' => 636,
            'ldap_security' => 'ldaps',
            'base_dn' => 'example.local',
            'discovery_username' => 'discovery@example.local',
            'discovery_password' => 'discovery-secret',
            'monitor_username' => 'monitor@example.local',
            'auth_method' => 'ssh_private_key',
            'ssh_private_key' => UploadedFile::fake()->createWithContent('id_rsa', $privateKey),
            'threshold_uptime' => 365,
            'threshold_updates_available' => 3,
        ])
        ->assertRedirect(route('configuration.index'));

    $domain = WindowsDomain::query()->firstOrFail();
    $rawDomain = WindowsDomain::query()->toBase()->first();
    $encryptedKey = Storage::disk('private_keys')->get($domain->ssh_private_key);

    expect($domain->base_dn)->toBe('DC=example,DC=local')
        ->and($domain->discovery_password)->toBe('discovery-secret')
        ->and($rawDomain->discovery_password)->not->toContain('discovery-secret')
        ->and(Crypt::decryptString($encryptedKey))->toBe($privateKey);
});

test('removing a domain removes its monitors and private key', function () {
    Storage::fake('private_keys');
    Storage::disk('private_keys')->put('domain.key', Crypt::encryptString('private-key'));
    $domain = configuredWindowsDomain([
        'auth_method' => 'ssh_private_key',
        'monitor_password' => null,
        'ssh_private_key' => 'domain.key',
    ]);
    $monitor = new Monitor;
    $monitor->windows_domain_id = $domain->id;
    $monitor->name = 'Managed workstation';
    $monitor->hostname_ip = 'managed.example.local';
    $monitor->username = $domain->monitor_username;
    $monitor->auth_method = 'ssh_private_key';
    $monitor->ssh_private_key = 'domain.key';
    $monitor->save();

    $this->actingAs(User::factory()->create())
        ->delete(route('configuration.domains.destroy', $domain))
        ->assertRedirect(route('configuration.index'));

    expect(WindowsDomain::query()->count())->toBe(0)
        ->and(Monitor::query()->count())->toBe(0);
    Storage::disk('private_keys')->assertMissing('domain.key');
});

test('domain monitors are visually identified and cannot be edited directly', function () {
    $domain = configuredWindowsDomain();
    $monitor = new Monitor;
    $monitor->windows_domain_id = $domain->id;
    $monitor->name = 'Managed workstation';
    $monitor->hostname_ip = 'managed.example.local';
    $monitor->username = $domain->monitor_username;
    $monitor->auth_method = 'password';
    $monitor->password = Crypt::encryptString('monitor-secret');
    $monitor->save();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('domain-managed', false)
        ->assertSee('Corporate domain');

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertForbidden();
});

test('configuration routes require authentication', function () {
    $this->get(route('configuration.index'))->assertRedirect(route('login'));
});
