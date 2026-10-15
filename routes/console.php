<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// php artisan mire:admin someone@email.com           → make them admin (they can open /admin)
// php artisan mire:admin someone@email.com --remove  → take it away
Artisan::command('mire:admin {email} {--remove}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error("No user with the email {$email}.");

        return 1;
    }

    $user->forceFill(['is_admin' => ! $this->option('remove')])->save();                            // ← forceFill: is_admin isn't fillable on purpose
    $this->info($user->is_admin ? "{$user->name} is now an admin." : "{$user->name} is no longer an admin.");
})->purpose('Give or remove admin access');
