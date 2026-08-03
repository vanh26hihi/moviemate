<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_new_user_can_register_and_is_logged_in(): void
    {
        $this->post(route('register.store'), $this->validPayload())
            ->assertRedirect(route('home'));

        $user = User::query()->sole();

        $this->assertSame('new.user@example.com', $user->email);
        $this->assertTrue(Hash::check('secure-password', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_normalizes_email_and_persists_supported_phone(): void
    {
        $payload = $this->validPayload([
            'email' => '  NEW.USER@EXAMPLE.COM ',
            'phone' => ' 0901234567 ',
        ]);

        $this->post(route('register.store'), $payload)->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.user@example.com',
            'phone' => '0901234567',
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'new.user@example.com']);

        $this->from(route('register'))
            ->post(route('register.store'), $this->validPayload())
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_password_confirmation_mismatch(): void
    {
        $this->post(route('register.store'), $this->validPayload([
            'password_confirmation' => 'different-password',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_rejects_a_short_password(): void
    {
        $this->post(route('register.store'), $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $payload = $this->validPayload();
        unset($payload['terms']);

        $this->post(route('register.store'), $payload)
            ->assertSessionHasErrors('terms');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_ignores_role_escalation_and_creates_an_active_user_role(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Admin'],
            ['id' => 2, 'name' => 'Staff'],
            ['id' => 3, 'name' => 'User'],
        ]);

        $payload = $this->validPayload([
            'role_id' => 1,
            'role' => 'Admin',
            'status' => 'inactive',
            'is_admin' => true,
        ]);

        $this->post(route('register.store'), $payload)->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', [
            'email' => 'new.user@example.com',
            'role_id' => 3,
            'status' => 'active',
        ]);
    }

    public function test_registration_redirects_to_the_intended_url(): void
    {
        $this->withSession(['url.intended' => route('user.profile')])
            ->post(route('register.store'), $this->validPayload())
            ->assertRedirect(route('user.profile'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New User',
            'email' => 'new.user@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'terms' => '1',
        ], $overrides);
    }
}
