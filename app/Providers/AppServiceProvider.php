<?php

namespace App\Providers;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Ai\MovieMateToolCallGuard;
use App\Models\AiConversation;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Policies\AiConversationPolicy;
use App\Policies\BookingPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Services\BookingCheckoutDraftService;
use App\Services\CinemaAccessService;
use App\Services\CinemaContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Tools\ToolNameResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CinemaContext::class, fn () => new CinemaContext);
        $this->app->scoped(CinemaAccessService::class, fn () => new CinemaAccessService);
        $this->app->scoped(MovieMateToolCallGuard::class, fn () => new MovieMateToolCallGuard);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(InvokingTool::class, function (InvokingTool $event): void {
            if ($event->agent instanceof MovieMateCinemaAssistant) {
                app(MovieMateToolCallGuard::class)->record(
                    ToolNameResolver::resolve($event->tool),
                    $event->arguments,
                );
            }
        });

        RateLimiter::for('ai-chat', fn (Request $request): array => $this->aiRateLimits(
            $request,
            'chat',
            'Bạn đã gửi quá nhiều yêu cầu cho trợ lý. Vui lòng chờ rồi thử lại.',
        ));
        RateLimiter::for('ai-recommendation', fn (Request $request): array => $this->aiRateLimits(
            $request,
            'recommend',
            'Bạn đã yêu cầu quá nhiều gợi ý. Vui lòng chờ rồi thử lại.',
        ));

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

        Gate::policy(AiConversation::class, AiConversationPolicy::class);
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
            $view->with([
                'adminAccessibleCinemas' => $adminCinemas,
                'adminCurrentCinema' => $adminCurrentCinema,
                'adminHasGlobalCinemaAccess' => $user ? $access->hasGlobalAccess($user) : false,
            ]);
        });

        View::composer('layouts.staff', function ($view): void {
            $access = app(CinemaAccessService::class);
            $user = auth()->user();
            $view->with([
                'staffAccessibleCinemas' => $user ? $access->accessibleCinemas($user) : collect(),
                'staffCurrentCinema' => $user ? $access->currentCinema($user) : null,
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

    /** @return list<Limit> */
    private function aiRateLimits(Request $request, string $scope, string $message): array
    {
        $authenticated = $request->user() !== null;
        $audience = $authenticated ? 'user' : 'guest';
        $identity = $authenticated ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.hash('sha256', $request->ip());
        $config = (array) config('moviemate-ai.rate_limits', []);
        $response = fn (Request $request, array $headers) => $request->expectsJson()
            ? response()->json(['message' => $message], 429, $headers)
            : response($message, 429, $headers);

        return [
            Limit::perMinute(max(1, (int) ($config[$scope.'_'.$audience.'_minute'] ?? 8)))
                ->by("ai:{$scope}:minute:{$identity}")->response($response),
            Limit::perHour(max(1, (int) ($config[$scope.'_'.$audience.'_hour'] ?? 40)))
                ->by("ai:{$scope}:hour:{$identity}")->response($response),
        ];
    }
}
