<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Services\Admin\PaymentReconciliationQuery;
use App\Services\BookingCheckoutDraftService;
use App\Services\CinemaAccessService;
use App\Services\CinemaContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CinemaContext::class, fn () => new CinemaContext);
        $this->app->scoped(CinemaAccessService::class, fn () => new CinemaAccessService);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('booking-hold-creation', function (Request $request): array {
            $keys = app(BookingCheckoutDraftService::class)->holdCreationRateLimitKeys($request);
            $message = 'Bạn đã tạo quá nhiều lượt giữ ghế trong thời gian ngắn. Vui lòng chờ một chút rồi thử lại.';
            $response = function (Request $request, array $headers) use ($message) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 429, $headers);
                }

                return response($message, 429, $headers);
            };
            $window = max(1, (int) config('booking.hold_creation_window_minutes', 10));

            return [
                Limit::perMinutes($window, max(1, (int) config('booking.hold_creation_max_attempts', 4)))
                    ->by($keys['primary'])->response($response),
                Limit::perMinutes($window, max(1, (int) config('booking.hold_creation_max_attempts', 4)))
                    ->by($keys['session'])->response($response),
                Limit::perMinutes($window, max(1, (int) config('booking.hold_creation_network_max_attempts', 20)))
                    ->by($keys['network'])->response($response),
            ];
        });

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

        View::composer('layouts.admin', function ($view): void {
            $access = app(CinemaAccessService::class);
            $user = auth()->user();
            $adminCinemas = $user ? $access->accessibleCinemas($user) : collect();
            $adminCurrentCinema = $user ? $access->resolve($user) : null;
            $badge = auth()->check() && auth()->user()->can('payments.reconcile')
                ? app(PaymentReconciliationQuery::class)->badgeLabel()
                : null;
            $view->with([
                'paymentReconciliationBadge' => $badge,
                'adminAccessibleCinemas' => $adminCinemas,
                'adminCurrentCinema' => $adminCurrentCinema,
                'adminHasGlobalCinemaAccess' => $user ? $access->hasGlobalAccess($user) : false,
            ]);
        });

        View::composer('layouts.user', function ($view): void {
            if (! Schema::hasTable('cinemas')) {
                return;
            }
            $context = app(CinemaContext::class);
            $view->with([
                'customerCinemas' => $context->activeCinemas(),
                'customerPreferredCinema' => $context->preference(),
            ]);
        });
    }
}
