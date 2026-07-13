<?php

use App\Models\User;

test('profile management is disabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')->assertNotFound();
    $this->actingAs($user)->patch('/profile')->assertNotFound();
    $this->actingAs($user)->delete('/profile')->assertNotFound();
});
