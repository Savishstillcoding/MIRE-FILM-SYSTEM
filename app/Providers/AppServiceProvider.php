<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // "admin" permission: routes/web.php locks /admin with ->middleware('can:admin'); views use @can('admin').
        Gate::define('admin', fn (User $user) => $user->is_admin);

        Paginator::useBootstrapFive();                                                              // ← page links styled with Bootstrap (admin tables)

        // NEW: password strength for sign-up (AuthController::register uses Password::defaults()).
        // The checklist in partials/join-modal.blade.php shows the same rules live (app.js "data-password-rules").
        Password::defaults(function () {
            $rule = Password::min(8)                                                                // ← at least 8 characters
                ->mixedCase()                                                                       // ← an UPPERCASE and a lowercase letter
                ->numbers()                                                                         // ← a number
                ->symbols();                                                                        // ← a symbol, e.g. ! @ # ?

            // Live site only: also refuse passwords found in known data leaks (haveibeenpwned.com;
            // only the first 5 characters of the password's hash are sent, never the password).
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
