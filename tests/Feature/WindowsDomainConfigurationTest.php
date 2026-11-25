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
        ->assertSee('data-domain-filter="'.$domain->getKey().'"', false)
        ->assertSee('data-domain-id="'.$domain->getKey().'"', false)
        ->assertSee('Corporate domain');

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertForbidden();
});

test('configuration routes require authentication', function () {
    $this->get(route('configuration.index'))->assertRedirect(route('login'));
});

test('domain switch persists both states without discovery or deleting computers', function () {
    $domain = configuredWindowsDomain();
    $monitor = domainToggleMonitor($domain);
    $this->mock(WindowsDomainSynchronizer::class)->shouldNotReceive('sync');
    $this->actingAs(User::factory()->create());

    $this->get(route('configuration.index'))->assertOk()
        ->assertSee('role="switch"', false)
        ->assertSee('aria-checked="true"', false);

    foreach ([false, true] as $enabled) {
        $this->patch(route('configuration.domains.enabled', $domain), ['enabled' => $enabled])
            ->assertRedirect(route('configuration.index'));
        expect($domain->fresh()->enabled)->toBe($enabled)
            ->and($monitor->fresh())->not->toBeNull();
        $this->get(route('configuration.index'))->assertOk()
            ->assertSee('aria-checked="'.($enabled ? 'true' : 'false').'"', false);
    }

    $this->patch(route('configuration.domains.enabled', $domain), ['enabled' => 'invalid'])
        ->assertSessionHasErrors('enabled');
});

function domainToggleMonitor(?WindowsDomain $domain, string $name = 'Hidden workstation'): Monitor
{
    $monitor = new Monitor;
    $monitor->windows_domain_id = $domain?->id;
    $monitor->name = $name;
    $monitor->hostname_ip = 'workstation.example.local';
    $monitor->username = 'monitor';
    $monitor->auth_method = 'password';
    $monitor->password = Crypt::encryptString('secret');
    $monitor->save();

    return $monitor;
}

test('disabled domains and their computers disappear from the dashboard and return when enabled', function () {
    $domain = configuredWindowsDomain(['enabled' => false]);
    $hidden = domainToggleMonitor($domain);
    $standalone = domainToggleMonitor(null, 'Standalone server');
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard.index'))->assertOk()
        ->assertDontSee($domain->name)->assertDontSee($hidden->name)
        ->assertSee($standalone->name)
        ->assertViewHas('monitors', fn ($monitors) => $monitors->modelKeys() === [$standalone->id])
        ->assertViewHas('windowsDomains', fn ($domains) => $domains->isEmpty());

    $this->post(route('monitors.refresh', $hidden))->assertForbidden();
    $domain->update(['enabled' => true]);
    $this->get(route('dashboard.index'))->assertOk()
        ->assertSee($domain->name)->assertSee($hidden->name)->assertSee($standalone->name);
});

test('disabled domain discovery is skipped and existing computers are preserved', function () {
    $domain = configuredWindowsDomain(['enabled' => false]);
    $monitor = domainToggleMonitor($domain);
    $discovery = Mockery::mock(WindowsDomainDiscoveryService::class);
    $discovery->shouldNotReceive('discover');

    expect((new WindowsDomainSynchronizer($discovery))->sync($domain))->toBe(0)
        ->and($monitor->fresh())->not->toBeNull()
        ->and($domain->fresh()->last_discovered_at)->toBeNull();

    $this->mock(WindowsDomainSynchronizer::class)->shouldNotReceive('sync');
    $this->actingAs(User::factory()->create())
        ->post(route('configuration.domains.sync', $domain))
        ->assertRedirect(route('configuration.index'));
});

test('monitor commands skip disabled domains in sequential parallel and targeted scans', function (array $options, bool $targeted) {
    $domain = configuredWindowsDomain(['enabled' => false]);
    $monitor = domainToggleMonitor($domain);
    $this->mock(WindowsDomainSynchronizer::class)->shouldNotReceive('sync');
    Illuminate\Support\Facades\Process::fake();

    if ($targeted) {
        $options['--monitor'] = $monitor->id;
    }

    $this->artisan('app:monitor', $options)->assertExitCode($targeted ? 1 : 0);
    Illuminate\Support\Facades\Process::assertNothingRan();
    expect($monitor->fresh()->updated_at->equalTo($monitor->updated_at))->toBeTrue();
})->with([
    'sequential' => [['--force' => true], false],
    'parallel' => [['--force' => true, '--concurrency' => 5], false],
    'without discovery' => [['--force' => true, '--skip-windows-domain' => true], false],
    'targeted worker' => [['--force' => true, '--no-refresh' => true], true],
]);

test('domain switch requires authentication', function () {
    $domain = configuredWindowsDomain();
    $this->patch(route('configuration.domains.enabled', $domain), ['enabled' => false])
        ->assertRedirect(route('login'));
    expect($domain->fresh()->enabled)->toBeTrue();
});
