<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
        $this->configureGoogle();
    }

    public function test_login_and_register_show_google_action_only_when_configured(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Tiếp tục với Google')
            ->assertSee(route('auth.google.redirect'), false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Tiếp tục với Google')
            ->assertSee(route('auth.google.redirect'), false);

        config()->set('services.google.client_secret', null);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Google chưa được cấu hình')
            ->assertDontSee('Tiếp tục với Google');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Google chưa được cấu hình')
            ->assertDontSee('Tiếp tục với Google');
    }

    public function test_unconfigured_redirect_fails_safely_without_contacting_provider(): void
    {
        config()->set('services.google.client_id', null);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Đăng nhập Google chưa được cấu hình.');

        $this->assertGuest();
    }

    public function test_configured_redirect_uses_expected_google_scopes(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('scopes')
            ->once()
            ->with(['openid', 'email', 'profile'])
            ->andReturnSelf();
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.test/oauth'));

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect('https://accounts.google.test/oauth');
    }

    public function test_verified_google_identity_creates_active_customer_and_regenerates_session(): void
    {
        $oldSessionId = session()->getId();
        $this->fakeGoogleUser([
            'id' => 'google-new-customer',
            'name' => 'Google Customer',
            'email' => '  New.Customer@Example.COM ',
            'email_verified' => true,
            'token' => 'access-token-must-not-be-stored',
            'refreshToken' => 'refresh-token-must-not-be-stored',
        ]);

        $response = $this->get(route('auth.google.callback', [
            'role' => 'admin',
            'status' => 'inactive',
        ]));

        $response->assertRedirect(route('home'));
        $user = User::query()->where('google_id', 'google-new-customer')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('new.customer@example.com', $user->email);
        $this->assertSame('user', $user->role->slug);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::isHashed($user->getRawOriginal('password')));
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertFalse(Schema::hasColumn('users', 'google_token'));
        $this->assertFalse(Schema::hasColumn('users', 'google_refresh_token'));
        $this->assertFalse(Schema::hasColumn('users', 'access_token'));
        $this->assertFalse(Schema::hasColumn('users', 'refresh_token'));
    }

    public function test_verified_google_identity_links_existing_active_customer_without_changing_password(): void
    {
        $customer = $this->userWithRole('user', [
            'email' => 'existing@example.com',
            'email_verified_at' => null,
            'password' => Hash::make('keep-this-password'),
        ]);
        $passwordHash = $customer->getRawOriginal('password');
        $this->fakeGoogleUser([
            'id' => 'google-existing',
            'email' => 'EXISTING@example.com',
            'email_verified' => true,
        ]);

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $customer->refresh();
        $this->assertAuthenticatedAs($customer);
        $this->assertSame('google-existing', $customer->google_id);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertSame($passwordHash, $customer->getRawOriginal('password'));
        $this->assertSame(1, User::query()->where('email', 'existing@example.com')->count());
    }

    public function test_already_linked_customer_can_log_in_and_provider_email_cannot_replace_local_email(): void
    {
        $customer = $this->userWithRole('user', [
            'email' => 'linked@example.com',
            'google_id' => 'google-linked',
        ]);
        $this->fakeGoogleUser([
            'id' => 'google-linked',
            'email' => 'changed-at-provider@example.com',
            'email_verified' => true,
        ]);

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($customer);
        $this->assertSame('linked@example.com', $customer->fresh()->email);
        $this->assertDatabaseMissing('users', ['email' => 'changed-at-provider@example.com']);
    }

    public function test_email_linked_to_different_google_identity_is_rejected_without_mutation(): void
    {
        $customer = $this->userWithRole('user', [
            'email' => 'collision@example.com',
            'google_id' => 'google-original',
        ]);
        $this->fakeGoogleUser([
            'id' => 'google-attacker',
            'email' => 'collision@example.com',
            'email_verified' => true,
        ]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Email này đã được liên kết với một tài khoản Google khác.');

        $this->assertGuest();
        $this->assertSame('google-original', $customer->fresh()->google_id);
    }

    public function test_privileged_email_collisions_are_rejected_without_linking(): void
    {
        foreach (['admin', 'manager', 'staff'] as $role) {
            $user = $this->userWithRole($role, ['email' => $role.'@example.com']);
            $this->fakeGoogleUser([
                'id' => 'google-'.$role,
                'email' => $role.'@example.com',
                'email_verified' => true,
            ]);

            $this->get(route('auth.google.callback'))
                ->assertRedirect(route('login'))
                ->assertSessionHas('error', 'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.');

            $this->assertGuest();
            $this->assertNull($user->fresh()->google_id);
        }
    }

    public function test_linked_privileged_account_is_still_rejected(): void
    {
        $admin = $this->userWithRole('admin', [
            'email' => 'linked-admin@example.com',
            'google_id' => 'google-linked-admin',
        ]);
        $this->fakeGoogleUser([
            'id' => 'google-linked-admin',
            'email' => 'linked-admin@example.com',
            'email_verified' => true,
        ]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tài khoản nhân sự không thể đăng nhập qua cổng Google dành cho khách hàng.');

        $this->assertGuest();
        $this->assertSame('google-linked-admin', $admin->fresh()->google_id);
    }

    public function test_inactive_customer_is_rejected_without_being_linked(): void
    {
        $customer = $this->userWithRole('user', [
            'email' => 'inactive@example.com',
            'status' => 'inactive',
        ]);
        $this->fakeGoogleUser([
            'id' => 'google-inactive',
            'email' => 'inactive@example.com',
            'email_verified' => true,
        ]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tài khoản hiện không thể đăng nhập.');

        $this->assertGuest();
        $this->assertNull($customer->fresh()->google_id);
    }

    public function test_unverified_or_missing_email_is_rejected_without_creating_user(): void
    {
        $this->fakeGoogleUser([
            'id' => 'google-unverified',
            'email' => 'unverified@example.com',
            'email_verified' => false,
        ]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Email Google chưa được xác minh nên không thể đăng nhập.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unverified@example.com']);

        $this->fakeGoogleUser([
            'id' => 'google-missing-email',
            'email' => null,
            'email_verified' => true,
        ]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tài khoản Google phải cung cấp địa chỉ email hợp lệ.');

        $this->assertGuest();
    }

    public function test_missing_provider_identifier_is_rejected_without_creating_user(): void
    {
        $this->fakeGoogleUser([
            'id' => '',
            'email' => 'no-provider-id@example.com',
            'email_verified' => true,
        ]);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google không cung cấp mã tài khoản hợp lệ.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'no-provider-id@example.com']);
    }

    public function test_provider_error_and_explicit_customer_cancellation_fail_safely(): void
    {
        Socialite::fake('google', function (): never {
            throw new RuntimeException('secret provider detail that must not reach the user');
        });

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Không thể đăng nhập bằng Google lúc này. Vui lòng thử lại.')
            ->assertSessionMissing('secret provider detail that must not reach the user');

        $this->assertGuest();

        $this->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Đăng nhập Google đã bị hủy. Vui lòng thử lại nếu bạn muốn tiếp tục.');

        $this->assertGuest();
    }

    public function test_google_callback_honors_the_existing_internal_intended_destination(): void
    {
        $this->fakeGoogleUser([
            'id' => 'google-intended',
            'email' => 'intended@example.com',
            'email_verified' => true,
        ]);

        $this->withSession(['url.intended' => route('user.bookings.history')])
            ->get(route('auth.google.callback'))
            ->assertRedirect(route('user.bookings.history'));
    }

    private function configureGoogle(): void
    {
        config()->set('services.google', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fakeGoogleUser(array $attributes): void
    {
        Socialite::fake('google', GoogleUser::fake($attributes));
    }
}
