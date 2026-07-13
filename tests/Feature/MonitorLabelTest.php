<?php

use App\Models\Label;
use App\Models\Monitor;
use App\Models\User;

function createMonitorForLabels(string $name = 'Labelled server'): Monitor
{
    $monitor = new Monitor;
    $monitor->name = $name;
    $monitor->hostname_ip = '192.0.2.1';
    $monitor->username = 'keepup';
    $monitor->auth_method = 'password';
    $monitor->password = 'encrypted-password';
    $monitor->save();

    return $monitor;
}

test('a user can add multiple labels to a monitor', function () {
    $user = User::factory()->create();
    $monitor = createMonitorForLabels();

    $this->actingAs($user)
        ->postJson(route('monitors.labels.store', $monitor), ['label' => ' Production '])
        ->assertCreated()
        ->assertJsonPath('label.name', 'Production');

    $this->actingAs($user)
        ->postJson(route('monitors.labels.store', $monitor), ['label' => 'Customer A'])
        ->assertCreated();

    expect($monitor->fresh()->labels->pluck('name')->all())
        ->toEqualCanonicalizing(['Production', 'Customer A']);
});

test('labels are shared case insensitively without duplicate monitor assignments', function () {
    $user = User::factory()->create();
    $firstMonitor = createMonitorForLabels('First server');
    $secondMonitor = createMonitorForLabels('Second server');

    $this->actingAs($user)
        ->postJson(route('monitors.labels.store', $firstMonitor), ['label' => 'Production'])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('monitors.labels.store', $firstMonitor), ['label' => 'production'])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('monitors.labels.store', $secondMonitor), ['label' => 'PRODUCTION'])
        ->assertCreated();

    expect(Label::query()->count())->toBe(1)
        ->and($firstMonitor->fresh()->labels)->toHaveCount(1)
        ->and($secondMonitor->fresh()->labels)->toHaveCount(1);
});

test('a label can be removed from a monitor', function () {
    $user = User::factory()->create();
    $monitor = createMonitorForLabels();
    $label = Label::create(['name' => 'Legacy', 'normalized_name' => 'legacy']);
    $monitor->labels()->attach($label);

    $this->actingAs($user)
        ->deleteJson(route('monitors.labels.destroy', [$monitor, $label]))
        ->assertOk()
        ->assertJson(['status' => true]);

    expect($monitor->fresh()->labels)->toBeEmpty()
        ->and(Label::query()->find($label->getKey()))->toBeNull();
});

test('labels still used by another monitor are retained', function () {
    $user = User::factory()->create();
    $firstMonitor = createMonitorForLabels('First server');
    $secondMonitor = createMonitorForLabels('Second server');
    $label = Label::create(['name' => 'Shared', 'normalized_name' => 'shared']);
    $firstMonitor->labels()->attach($label);
    $secondMonitor->labels()->attach($label);

    $this->actingAs($user)
        ->deleteJson(route('monitors.labels.destroy', [$firstMonitor, $label]))
        ->assertOk();

    expect(Label::query()->find($label->getKey()))->not->toBeNull()
        ->and($secondMonitor->fresh()->labels)->toHaveCount(1);
});

test('guests cannot change monitor labels', function () {
    $monitor = createMonitorForLabels();
    $label = Label::create(['name' => 'Protected', 'normalized_name' => 'protected']);
    $monitor->labels()->attach($label);

    $this->postJson(route('monitors.labels.store', $monitor), ['label' => 'New'])
        ->assertUnauthorized();

    $this->deleteJson(route('monitors.labels.destroy', [$monitor, $label]))
        ->assertUnauthorized();
});

test('the dashboard renders label filters and monitor label metadata', function () {
    $user = User::factory()->create();
    $monitor = createMonitorForLabels();
    $label = Label::create(['name' => 'Production', 'normalized_name' => 'production']);
    $monitor->labels()->attach($label);

    $this->actingAs($user)
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('data-label-filter="'.$label->getKey().'"', false)
        ->assertSee('data-label-ids="'.$label->getKey().'"', false)
        ->assertSee('Production');
});

test('label colors are stable hex values derived from normalized names', function () {
    $label = new Label(['name' => 'Production', 'normalized_name' => 'production']);

    expect($label->color())->toBe('#ab8e18')
        ->and($label->textColor())->toBe('#ffffff')
        ->and($label->color())->toMatch('/^#[0-9a-f]{6}$/');
});
