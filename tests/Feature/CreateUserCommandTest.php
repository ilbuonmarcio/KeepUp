<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a verified user can be created interactively', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('Name', 'KeepUp Admin')
        ->expectsQuestion('Email address', 'ADMIN@example.com')
        ->expectsQuestion('Password (minimum 12 characters)', 'a-secure-password')
        ->expectsQuestion('Confirm password', 'a-secure-password')
        ->expectsOutputToContain('User admin@example.com created.')
        ->assertSuccessful();

    $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($user->name)->toBe('KeepUp Admin')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-secure-password', $user->password))->toBeTrue();
});

test('invalid user details are rejected', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('Name', '')
        ->expectsQuestion('Email address', 'not-an-email')
        ->expectsQuestion('Password (minimum 12 characters)', 'short')
        ->expectsQuestion('Confirm password', 'different')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
