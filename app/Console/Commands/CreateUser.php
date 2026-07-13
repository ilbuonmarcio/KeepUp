<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Create a verified KeepUp user interactively';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Name'));
        $email = Str::lower(trim((string) $this->ask('Email address')));
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->email_verified_at = now();
        $user->password = $password;
        $user->save();

        $this->components->info("User {$user->email} created.");

        return self::SUCCESS;
    }
}
