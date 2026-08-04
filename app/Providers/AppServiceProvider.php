<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Services\CinemaContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CinemaContext::class, fn () => new CinemaContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function ($user, string $ability): ?bool {
            if (! $user->isActive()) {
                return false;
            }

            return str_contains($ability, '.') && $user->hasPermission($ability)
                ? true
                : null;
        });
    }
}
