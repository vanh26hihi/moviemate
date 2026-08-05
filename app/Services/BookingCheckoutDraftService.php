<?php

namespace App\Services;

use App\Exceptions\BookingCheckoutConflictException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BookingCheckoutDraftService
{
    private const SESSION_KEY = 'booking_checkout_draft';

    private const GUEST_ACTOR_KEY = 'booking_checkout_guest_actor';

    public function __construct(
        private readonly BookingTokenService $tokens,
        private readonly FoodSelectionCanonicalizer $foodSelections,
    ) {}

    public function start(Request $request, int $showtimeId, array $seatIds): array
    {
        $draft = [
            'showtime_id' => $showtimeId,
            'seat_ids' => $this->normalizeSeatIds($seatIds),
            'food_items' => [],
            'customer_email' => $this->normalizedEmail($request->user()?->email),
            'checkout_token' => $this->tokens->issueCheckoutToken(),
            'actor_identity' => $this->actorIdentity($request),
            'created_at' => now()->timestamp,
        ];

        $request->session()->put(self::SESSION_KEY, $draft);

        return $draft;
    }

    public function current(Request $request, bool $requireEmail = false): array
    {
        $draft = $request->session()->get(self::SESSION_KEY);
        if (! is_array($draft)
            || ! is_int($draft['showtime_id'] ?? null)
            || ! is_array($draft['seat_ids'] ?? null)
            || ! is_array($draft['food_items'] ?? null)
            || ! is_string($draft['checkout_token'] ?? null)
            || ! $this->tokens->isValidCheckoutToken($draft['checkout_token'])) {
            throw ValidationException::withMessages([
                'checkout' => 'Phiên checkout đã hết hạn. Vui lòng chọn lại ghế.',
            ]);
        }

        if (! is_string($draft['actor_identity'] ?? null)
            || ! hash_equals($draft['actor_identity'], $this->actorIdentity($request))) {
            throw new BookingCheckoutConflictException;
        }

        if ($requireEmail && ! filter_var($draft['customer_email'] ?? null, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'customer_email' => 'Vui lòng cung cấp email hợp lệ để nhận vé.',
            ]);
        }

        return $draft;
    }

    public function updateContactAndFood(
        Request $request,
        string $customerEmail,
        array|Collection|null $foodSelection,
    ): array {
        $draft = $this->current($request);
        $draft['customer_email'] = $this->normalizedEmail($customerEmail);
        $draft['food_items'] = $this->foodSelections->canonicalize($foodSelection);
        $request->session()->put(self::SESSION_KEY, $draft);

        return $draft;
    }

    private function actorIdentity(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();
        if ($userId !== null) {
            return 'user:'.$userId;
        }

        $guestActor = $request->session()->get(self::GUEST_ACTOR_KEY);
        if (! is_string($guestActor) || preg_match('/^[a-f0-9]{64}$/D', $guestActor) !== 1) {
            $guestActor = bin2hex(random_bytes(32));
            $request->session()->put(self::GUEST_ACTOR_KEY, $guestActor);
        }

        return 'guest-session:'.$guestActor;
    }

    private function normalizeSeatIds(array $seatIds): array
    {
        return collect($seatIds)
            ->map(fn ($seatId): int => (int) $seatId)
            ->filter(fn (int $seatId): bool => $seatId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizedEmail(mixed $email): string
    {
        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }
}
