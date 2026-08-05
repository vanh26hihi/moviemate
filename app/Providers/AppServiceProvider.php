<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Services\BookingCheckoutDraftService;
use App\Services\CinemaContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('booking-food-mutation', function (Request $request): Limit {
            $drafts = app(BookingCheckoutDraftService::class);
            $message = 'Bạn thao tác quá nhanh. Vui lòng chờ vài giây rồi thử lại.';

            return Limit::perMinute(max(1, (int) config('booking.food_mutation_max_attempts', 6)))
                ->by($drafts->foodMutationRateLimitKey($request))
                ->response(function (Request $request, array $headers) use ($drafts, $message) {
                    if ($request->expectsJson()) {
                        return response()->json(['message' => $message], 429, $headers);
                    }

                    $route = $drafts->hasCurrent($request)
                        ? 'user.bookings.food'
                        : 'user.movies.index';

                    return redirect()->route($route)
                        ->with('warning', $message)
                        ->withHeaders($headers);
                });
        });

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
