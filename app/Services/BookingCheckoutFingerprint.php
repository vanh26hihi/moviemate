<?php

namespace App\Services;

use JsonException;

class BookingCheckoutFingerprint
{
    private const SCHEMA_VERSION = 'booking-checkout-v1';

    /**
     * @throws JsonException
     */
    public function hash(
        int $showtimeId,
        array $seatIds,
        string $customerEmail,
        ?int $userId,
    ): string {
        $normalizedSeats = collect($seatIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $normalizedEmail = mb_strtolower(trim($customerEmail));
        $actorType = $userId === null ? 'guest' : 'authenticated';
        $actorIdentity = $userId === null ? 'email:'.$normalizedEmail : 'user:'.$userId;

        $canonical = json_encode([
            'schema' => self::SCHEMA_VERSION,
            'showtime_id' => $showtimeId,
            'seat_ids' => $normalizedSeats,
            'customer_email' => $normalizedEmail,
            'actor_type' => $actorType,
            'actor_identity' => $actorIdentity,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return hash('sha256', $canonical);
    }

    public function matches(?string $storedHash, string $requestHash): bool
    {
        return is_string($storedHash)
            && preg_match('/^[a-f0-9]{64}$/D', $storedHash) === 1
            && hash_equals($storedHash, $requestHash);
    }
}
