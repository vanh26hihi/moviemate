<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class GuestBookingAccessService
{
    private const SESSION_KEY = 'guest_booking_capabilities';

    public function __construct(private readonly BookingTokenService $tokens) {}

    public function exchange(Request $request, Booking $booking, string $rawToken): bool
    {
        if ($booking->user_id !== null) {
            return false;
        }

        if ($this->validGuestToken($booking, $rawToken)) {
            $this->grant(
                $request,
                $booking,
                'guest',
                $booking->guest_access_token_hash,
                $booking->guest_access_expires_at,
            );

            return true;
        }

        if (! $this->validTicketEmailToken($booking, $rawToken)) {
            return false;
        }

        $this->grant(
            $request,
            $booking,
            'ticket-email',
            $booking->ticket_email_token_hash,
            $booking->ticket_email_token_expires_at,
        );

        return true;
    }

    public function hasExchangeableCredential(Booking $booking): bool
    {
        return $booking->user_id === null
            && ($this->hasCurrentGuestCredential($booking)
                || $this->hasCurrentTicketEmailCredential($booking));
    }

    private function grant(
        Request $request,
        Booking $booking,
        string $source,
        string $credentialHash,
        CarbonInterface $credentialExpiry,
    ): void {
        $sessionExpiry = now()->addMinutes(
            max(1, (int) config('booking.guest_session_ttl_minutes', 60)),
        );
        $expiresAt = $credentialExpiry->lt($sessionExpiry)
            ? $credentialExpiry
            : $sessionExpiry;
        $request->session()->migrate(true);
        $capabilities = $request->session()->get(self::SESSION_KEY, []);
        if (! is_array($capabilities)) {
            $capabilities = [];
        }
        $capabilities[(string) $booking->id] = [
            'expires_at' => $expiresAt->timestamp,
            'source' => $source,
            'token_hash' => $credentialHash,
        ];
        $request->session()->put(self::SESSION_KEY, $capabilities);
    }

    public function allows(Request $request, Booking $booking): bool
    {
        if ($booking->user_id !== null) {
            return false;
        }

        $capabilities = $request->session()->get(self::SESSION_KEY, []);
        if (! is_array($capabilities)) {
            return false;
        }
        $grant = $capabilities[(string) $booking->id] ?? null;

        $valid = is_array($grant)
            && is_int($grant['expires_at'] ?? null)
            && $grant['expires_at'] > now()->timestamp;
        if ($valid) {
            $source = $grant['source'] ?? 'guest';
            $hash = $grant['token_hash'] ?? null;
            $valid = $source === 'guest'
                ? $this->grantMatchesGuestCredential($booking, $hash)
                : ($source === 'ticket-email'
                    && $this->grantMatchesTicketEmailCredential($booking, $hash));
        }

        if (! $valid) {
            unset($capabilities[(string) $booking->id]);
            $request->session()->put(self::SESSION_KEY, $capabilities);

            return false;
        }

        return true;
    }

    private function validGuestToken(Booking $booking, string $rawToken): bool
    {
        return $this->hasCurrentGuestCredential($booking)
            && $this->tokens->verifyHash($booking->guest_access_token_hash, $rawToken);
    }

    private function validTicketEmailToken(Booking $booking, string $rawToken): bool
    {
        return $this->hasCurrentTicketEmailCredential($booking)
            && $this->tokens->verifyHash($booking->ticket_email_token_hash, $rawToken);
    }

    private function hasCurrentGuestCredential(Booking $booking): bool
    {
        return $booking->guest_access_expires_at?->isFuture() === true
            && $this->isTokenHash($booking->guest_access_token_hash);
    }

    private function hasCurrentTicketEmailCredential(Booking $booking): bool
    {
        return $booking->ticket_email_token_expires_at?->isFuture() === true
            && $this->isTokenHash($booking->ticket_email_token_hash);
    }

    private function grantMatchesGuestCredential(Booking $booking, mixed $hash): bool
    {
        return $this->hasCurrentGuestCredential($booking)
            && is_string($hash)
            && hash_equals($booking->guest_access_token_hash, $hash);
    }

    private function grantMatchesTicketEmailCredential(Booking $booking, mixed $hash): bool
    {
        return $this->hasCurrentTicketEmailCredential($booking)
            && is_string($hash)
            && hash_equals($booking->ticket_email_token_hash, $hash);
    }

    private function isTokenHash(mixed $hash): bool
    {
        return is_string($hash)
            && strlen($hash) === 64
            && ctype_xdigit($hash);
    }
}
