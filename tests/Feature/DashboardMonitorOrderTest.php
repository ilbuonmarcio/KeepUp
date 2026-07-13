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
