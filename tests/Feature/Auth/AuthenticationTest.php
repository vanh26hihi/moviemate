<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false);
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_normalizes_email_before_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->post(route('login.store'), [
            'email' => '  MEMBER@EXAMPLE.COM  ',
            'password' => 'correct-password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_an_incorrect_password_with_a_generic_error(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_login_rejects_an_unknown_email_with_the_same_generic_error(): void
    {
        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->from(route('login'))->post(route('login.store'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_remember_me_sets_the_recaller_cookie(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-password',
            'remember' => '1',
        ]);

        $response->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);
        $this->startSession();
        $session = $this->app['session.store'];
        $oldSessionId = $session->getId();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $this->assertNotSame($oldSessionId, $session->getId());
    }

    public function test_login_redirects_to_the_intended_url(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->withSession(['url.intended' => route('user.profile')])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'correct-password',
            ])->assertRedirect(route('user.profile'));
    }

    public function test_inactive_user_cannot_log_in_when_status_is_supported(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);
        $user->status = 'inactive';
        $user->save();

        $this->withSession(['private-marker' => 'present']);
        $oldToken = $this->app['session.store']->token();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertSessionHasErrors(['email' => 'Tài khoản hiện không thể đăng nhập.']);

        $this->assertGuest();
        $this->assertFalse($this->app['session.store']->has('private-marker'));
        $this->assertNotSame($oldToken, $this->app['session.store']->token());
    }

    public function test_authenticated_user_is_redirected_away_from_guest_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect(route('home'));
        $this->actingAs($user)->get(route('register'))->assertRedirect(route('home'));
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_logout_invalidates_session_data_and_regenerates_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private-marker' => 'present']);
        $oldToken = $this->app['session.store']->token();

        $this->post(route('logout'));

        $this->assertFalse($this->app['session.store']->has('private-marker'));
        $this->assertNotSame($oldToken, $this->app['session.store']->token());
    }

    public function test_guest_cannot_submit_logout(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
