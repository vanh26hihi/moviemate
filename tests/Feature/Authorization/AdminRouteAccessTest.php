<?php

namespace Tests\Feature\Authorization;

use App\Models\Genre;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

class AdminRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_guest_is_redirected_from_admin_and_cannot_mutate_data(): void
    {
        $genre = Genre::query()->create(['name' => 'Action', 'slug' => 'action']);

        $this->get('/admin')->assertRedirect(route('login'));
        $this->delete(route('admin.genres.destroy', $genre))->assertRedirect(route('login'));

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    public function test_inactive_admin_is_logged_out_on_next_protected_request(): void
    {
        $admin = $this->userWithRole('admin', ['status' => 'inactive']);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_customer_and_staff_cannot_access_admin_crud_directly(): void
    {
        $this->actingAs($this->userWithRole('user'))
            ->get(route('admin.movies.index'))->assertForbidden();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.movies.index'))->assertForbidden();
    }

    public function test_manager_can_access_each_authorized_operations_module_but_not_security_modules(): void
    {
        $manager = $this->userWithRole('manager');
        $allowedRoutes = [
            'admin.dashboard', 'admin.movies.index', 'admin.genres.index',
            'admin.cinema.show', 'admin.rooms.index', 'admin.seats.index',
            'admin.showtimes.index', 'admin.foods.index', 'admin.food-orders.index',
            'admin.payment-reviews.index',
        ];

        foreach ($allowedRoutes as $route) {
            $this->actingAs($manager)->get(route($route))->assertOk();
        }

        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('admin.cinemas.create'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('admin.cinemas.destroy'));
        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_admin_can_access_admin_dashboard_users_and_roles(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
    }

    public function test_staff_panel_is_role_and_permission_protected(): void
    {
        $staff = $this->userWithRole('staff');
        $customer = $this->userWithRole('user');

        $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('staff.tickets.index'))->assertOk();
        $this->actingAs($customer)->get(route('staff.dashboard'))->assertForbidden();
    }

    public function test_profile_and_history_require_an_active_authenticated_user(): void
    {
        $this->get(route('user.profile'))->assertRedirect(route('login'));
        $this->get(route('user.bookings.history'))->assertRedirect(route('login'));

        $customer = $this->userWithRole('user');
        $this->actingAs($customer)->get(route('user.profile'))->assertOk();
        $this->actingAs($customer)->get(route('user.bookings.history'))->assertOk();

        $customer->status = 'inactive';
        $customer->save();
        $this->get(route('user.profile'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_fallback_redirects_manager_and_staff_to_their_panels(): void
    {
        $manager = $this->userWithRole('manager');
        $staff = $this->userWithRole('staff');

        $this->post(route('login.store'), ['email' => $manager->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_missing_action_permission_denies_direct_url_even_with_admin_access(): void
    {
        $manager = $this->userWithRole('manager');
        $manager->role->permissions()->detach(Permission::query()->where('slug', 'movies.create')->value('id'));

        $this->actingAs($manager)->get(route('admin.movies.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.movies.create'))->assertForbidden();
    }

    public function test_every_protected_area_route_declares_required_middleware(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'admin') && ! str_starts_with($uri, 'staff') && $uri !== 'manager') {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, $uri.' must require auth');
            $this->assertContains('active', $middleware, $uri.' must require active account');

            if (str_starts_with($uri, 'admin')) {
                $this->assertContains('permission:admin.access', $middleware, $uri.' must require admin access');
                $this->assertTrue($this->hasPermissionMiddleware($route), $uri.' must require an action permission');
            } elseif ($uri === 'manager') {
                $this->assertContains('role:manager,admin', $middleware);
                $this->assertContains('permission:admin.access', $middleware);
            } else {
                $this->assertTrue($this->hasPermissionMiddleware($route), $uri.' must require a permission');
            }
        }
    }

    private function hasPermissionMiddleware(Route $route): bool
    {
        return collect($route->gatherMiddleware())->contains(
            fn (string $middleware) => str_starts_with($middleware, 'permission:') && $middleware !== 'permission:admin.access'
        );
    }
}
