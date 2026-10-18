<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('demo:create-user {email}', function () {
    if (app()->environment('production') || config('payfast.mode') !== 'sandbox') {
        throw new RuntimeException('Sandbox demo only.');
    }
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Supply a valid email address.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('User already exists; no changes made.');

        return 1;
    }
    $password = $this->secret('Choose a local demo password (12+ characters)');
    if (! is_string($password) || strlen($password) < 12) {
        $this->error('Password must have 12+ characters.');

        return 1;
    }
    User::create(['name' => 'Sandbox tester', 'email' => $email, 'password' => Hash::make($password)]);
    $this->info('Local sandbox user created.');
})->purpose('Create a local tester without a default or printed password');

Schedule::command('payfast:dispatch-outbox')->everyMinute()->withoutOverlapping();
