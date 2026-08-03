<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Http\Request;

class GuestBookingAccessService
{
    private const SESSION_KEY = 'guest_booking_capabilities';

    public function __construct(private readonly BookingTokenService $tokens) {}

    public function exchange(Request $request, Booking $booking, string $rawToken): bool
    {
        if ($booking->user_id !== null
            || ! $booking->guest_access_expires_at
            || ! $booking->guest_access_expires_at->isFuture()
            || ! $this->tokens->verifyHash(
                $booking->guest_access_token_hash,
                $rawToken,
            )) {
            return false;
        }

        $this->grant($request, $booking, true);

        return true;
    }

    public function grantPaymentReturn(Request $request, Booking $booking): bool
    {
        if ($booking->user_id !== null
            || ! $booking->guest_access_expires_at
            || ! $booking->guest_access_expires_at->isFuture()
            || ! is_string($booking->guest_access_token_hash)) {
            return false;
        }

        $this->grant($request, $booking, true);

        return true;
    }

    private function grant(Request $request, Booking $booking, bool $rotateSession): void
    {
        $sessionExpiry = now()->addMinutes(
            max(1, (int) config('booking.guest_session_ttl_minutes', 60)),
        );
        $expiresAt = $booking->guest_access_expires_at->lt($sessionExpiry)
            ? $booking->guest_access_expires_at
            : $sessionExpiry;
        if ($rotateSession) {
            $request->session()->migrate(true);
        }
        $capabilities = $request->session()->get(self::SESSION_KEY, []);
        if (! is_array($capabilities)) {
            $capabilities = [];
        }
        $capabilities[(string) $booking->id] = [
            'expires_at' => $expiresAt->timestamp,
            'token_hash' => $booking->guest_access_token_hash,
        ];
        $request->session()->put(self::SESSION_KEY, $capabilities);
    }

    public function allows(Request $request, Booking $booking): bool
    {
        if ($booking->user_id !== null
            || ! $booking->guest_access_expires_at
            || ! $booking->guest_access_expires_at->isFuture()) {
            return false;
        }

        $capabilities = $request->session()->get(self::SESSION_KEY, []);
        if (! is_array($capabilities)) {
            return false;
        }
        $grant = $capabilities[(string) $booking->id] ?? null;

        if (! is_array($grant)
            || ! is_int($grant['expires_at'] ?? null)
            || $grant['expires_at'] <= now()->timestamp
            || ! is_string($grant['token_hash'] ?? null)
            || ! is_string($booking->guest_access_token_hash)
            || ! hash_equals($booking->guest_access_token_hash, $grant['token_hash'])) {
            unset($capabilities[(string) $booking->id]);
            $request->session()->put(self::SESSION_KEY, $capabilities);

            return false;
        }

        return true;
    }
}
