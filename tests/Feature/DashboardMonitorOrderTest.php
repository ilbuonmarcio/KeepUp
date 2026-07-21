<?php

use App\Models\Monitor;
use App\Models\User;

test('dashboard monitors are ordered alphabetically by name', function () {
    foreach (['zulu server', 'Alpha server', 'beta server'] as $name) {
        $monitor = new Monitor;
        $monitor->name = $name;
        $monitor->hostname_ip = '192.0.2.1';
        $monitor->username = 'keepup';
        $monitor->auth_method = 'password';
        $monitor->password = 'encrypted-password';
        $monitor->save();
    }

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSeeInOrder(['Alpha server', 'beta server', 'zulu server']);
});

test('dashboard monitor table exposes sortable columns and values', function () {
    $monitor = new Monitor;
    $monitor->name = 'Sortable server';
    $monitor->hostname_ip = '192.0.2.1';
    $monitor->username = 'keepup';
    $monitor->auth_method = 'password';
    $monitor->password = 'encrypted-password';
    $monitor->latest_check_positive = true;
    $monitor->updates_available = 7;
    $monitor->uptime = 42;
    $monitor->save();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('data-sort-key="name"', false)
        ->assertSee('data-sort-key="status"', false)
        ->assertSee('data-sort-key="updates"', false)
        ->assertSee('data-sort-name="sortable server"', false)
        ->assertSee('data-sort-updates="7"', false)
        ->assertSee('data-sort-uptime="42"', false);
});
