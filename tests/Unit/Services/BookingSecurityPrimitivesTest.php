<?php

namespace Tests\Unit\Services;

use App\Exceptions\BookingTokenConfigurationException;
use App\Services\BookingCheckoutFingerprint;
use App\Services\BookingCodeGenerator;
use App\Services\BookingTokenService;
use Tests\TestCase;

class BookingSecurityPrimitivesTest extends TestCase
{
    public function test_checkout_fingerprint_is_canonical_and_actor_scoped(): void
    {
        $fingerprints = app(BookingCheckoutFingerprint::class);

        $canonical = $fingerprints->hash(10, [3, 1, 3], ' Guest@Example.Test ', null);
        $equivalent = $fingerprints->hash(10, [1, 3], 'guest@example.test', null);
        $authenticated = $fingerprints->hash(10, [1, 3], 'guest@example.test', 42);

        $this->assertSame($canonical, $equivalent);
        $this->assertNotSame($canonical, $authenticated);
        $this->assertTrue($fingerprints->matches($canonical, $equivalent));
        $this->assertFalse($fingerprints->matches(null, $canonical));
    }

    public function test_food_fingerprint_is_stable_across_line_order_and_ignores_client_prices(): void
    {
        $fingerprints = app(BookingCheckoutFingerprint::class);

        $canonical = $fingerprints->hash(10, [1], 'guest@example.test', null, [
            ['food_id' => 9, 'quantity' => '2', 'price' => 1],
            ['food_id' => 3, 'quantity' => 1, 'line_total' => 1],
        ]);
        $reordered = $fingerprints->hash(10, [1], 'guest@example.test', null, [
            ['food_id' => 3, 'quantity' => '1', 'price' => 999999],
            ['food_id' => 9, 'quantity' => 2, 'unit_price' => 999999],
        ]);

        $this->assertSame($canonical, $reordered);
    }

    public function test_food_fingerprint_changes_with_quantity_or_food_identity(): void
    {
        $fingerprints = app(BookingCheckoutFingerprint::class);
        $canonical = $fingerprints->hash(10, [1], 'guest@example.test', null, [
            ['food_id' => 3, 'quantity' => 1],
        ]);

        $this->assertNotSame($canonical, $fingerprints->hash(10, [1], 'guest@example.test', null, [
            ['food_id' => 3, 'quantity' => 2],
        ]));
        $this->assertNotSame($canonical, $fingerprints->hash(10, [1], 'guest@example.test', null, [
            ['food_id' => 4, 'quantity' => 1],
        ]));
    }

    public function test_empty_food_and_zero_quantities_have_the_same_fingerprint(): void
    {
        $fingerprints = app(BookingCheckoutFingerprint::class);

        $this->assertSame(
            $fingerprints->hash(10, [1], 'guest@example.test', null, []),
            $fingerprints->hash(10, [1], 'guest@example.test', null, [
                ['food_id' => 999, 'quantity' => 0, 'price' => 999999],
            ]),
        );
    }

    public function test_booking_codes_use_high_entropy_random_identifiers(): void
    {
        $generator = app(BookingCodeGenerator::class);
        $codes = collect(range(1, 100))->map(fn () => $generator->generate());

        $this->assertCount(100, $codes->unique());
        $this->assertTrue($codes->every(
            fn (string $code) => preg_match('/^MMT-\d{4}-[A-F0-9]{16}$/D', $code) === 1
        ));
    }

    public function test_booking_tokens_require_a_valid_256_bit_application_key(): void
    {
        config(['app.key' => 'short-key']);

        $this->expectException(BookingTokenConfigurationException::class);
        app(BookingTokenService::class)->issueCheckoutToken();
    }

    public function test_booking_tokens_reject_malformed_base64_application_keys(): void
    {
        config(['app.key' => 'base64:not-valid-***']);

        $this->expectException(BookingTokenConfigurationException::class);
        app(BookingTokenService::class)->issueCheckoutToken();
    }

    public function test_booking_tokens_accept_a_valid_base64_encoded_key(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        $tokens = app(BookingTokenService::class);

        $token = $tokens->issueCheckoutToken();

        $this->assertTrue($tokens->isValidCheckoutToken($token));
    }

    public function test_booking_tokens_expire_after_the_configured_checkout_ttl(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'booking.checkout_token_ttl_minutes' => 15,
        ]);
        $tokens = app(BookingTokenService::class);
        $token = $tokens->issueCheckoutToken();

        $this->travel(16)->minutes();

        $this->assertFalse($tokens->isValidCheckoutToken($token));
    }
}
