<?php

namespace App\Services;

use App\Exceptions\BookingCheckoutConflictException;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BookingCheckoutDraftService
{
    private const SESSION_KEY = 'booking_checkout_draft';

    private const GUEST_ACTOR_KEY = 'booking_checkout_guest_actor';

    private const ACTIVE_HOLDS_KEY = 'booking_checkout_active_holds';

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
            'discount_codes' => [],
            'points_to_use' => 0,
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
            || ! is_array($draft['discount_codes'] ?? null)
            || ! is_int($draft['points_to_use'] ?? null)
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

    public function hasCurrent(Request $request): bool
    {
        $draft = $request->session()->get(self::SESSION_KEY);

        return is_array($draft)
            && is_string($draft['checkout_token'] ?? null)
            && $this->tokens->isValidCheckoutToken($draft['checkout_token']);
    }

    /** @param list<string> $codes */
    public function updateDiscountCodes(Request $request, array $codes): array
    {
        $draft = $this->current($request, true);
        $draft['discount_codes'] = collect($codes)->map(fn ($code) => mb_strtoupper(trim((string) $code)))->filter()->unique()->values()->all();
        $request->session()->put(self::SESSION_KEY, $draft);

        return $draft;
    }

    public function promotionsAreLocked(Request $request): bool
    {
        $draft = $this->current($request, true);

        return Booking::query()
            ->where('checkout_idempotency_key_hash', $this->tokens->hash($draft['checkout_token']))
            ->whereHas('payments')
            ->exists();
    }

    public function updatePoints(Request $request, int $points): array
    {
        $draft = $this->current($request, true);
        if ($this->promotionsAreLocked($request)) {
            throw ValidationException::withMessages(['points' => 'Điểm đã được khóa cho lần thanh toán hiện tại.']);
        }
        $draft['points_to_use'] = max(0, $points);
        $request->session()->put(self::SESSION_KEY, $draft);

        return $draft;
    }

    public function foodMutationRateLimitKey(Request $request): string
    {
        $draft = $request->session()->get(self::SESSION_KEY);
        $actor = $request->user()?->getAuthIdentifier();
        $actorScope = $actor !== null
            ? 'user:'.$actor
            : (is_array($draft) && is_string($draft['actor_identity'] ?? null)
                ? $draft['actor_identity']
                : 'guest-session:'.$request->session()->getId());
        $checkoutScope = is_array($draft) && is_string($draft['checkout_token'] ?? null)
            ? $draft['checkout_token']
            : 'no-checkout-draft';

        return hash('sha256', $actorScope.'|checkout:'.$checkoutScope);
    }

    /** @return array{primary:string,session:string,network:string} */
    public function holdCreationRateLimitKeys(Request $request): array
    {
        $draft = $request->session()->get(self::SESSION_KEY);
        $showtime = is_array($draft) ? (int) ($draft['showtime_id'] ?? 0) : 0;
        $actor = is_array($draft) && is_string($draft['actor_identity'] ?? null)
            ? $draft['actor_identity']
            : $this->actorIdentity($request);
        $session = $request->session()->getId();
        $network = is_string($request->ip()) ? $request->ip() : 'unknown-network';
        $secret = (string) config('app.key', 'moviemate');

        return [
            'primary' => hash_hmac('sha256', $actor.'|showtime:'.$showtime, $secret),
            'session' => hash_hmac('sha256', 'session:'.$session.'|showtime:'.$showtime, $secret),
            'network' => hash_hmac('sha256', $network.'|showtime:'.$showtime, $secret),
        ];
    }

    public function assertMayCreateHold(Request $request, array $draft): void
    {
        $showtimeId = (int) $draft['showtime_id'];
        $checkoutHash = $this->tokens->hash((string) $draft['checkout_token']);
        $active = null;

        if ($request->user() !== null) {
            $active = Booking::query()
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->where('showtime_id', $showtimeId)
                ->where('booking_status', 'pending_payment')
                ->where('payment_status', 'unpaid')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();
        } else {
            $activeHolds = $request->session()->get(self::ACTIVE_HOLDS_KEY, []);
            $bookingId = is_array($activeHolds) ? ($activeHolds[$showtimeId] ?? null) : null;
            if (is_numeric($bookingId)) {
                $active = Booking::query()
                    ->whereKey((int) $bookingId)
                    ->where('showtime_id', $showtimeId)
                    ->where('booking_status', 'pending_payment')
                    ->where('payment_status', 'unpaid')
                    ->where('expires_at', '>', now())
                    ->first();
            }
        }

        if ($active !== null
            && ! hash_equals((string) $active->checkout_idempotency_key_hash, $checkoutHash)) {
            throw ValidationException::withMessages([
                'checkout' => 'Bạn đang có một lượt giữ ghế chưa thanh toán cho suất chiếu này. Vui lòng hoàn tất hoặc hủy lượt giữ hiện tại trước khi chọn lại.',
            ]);
        }
    }

    public function rememberActiveHold(Request $request, Booking $booking): void
    {
        if ($request->user() !== null) {
            return;
        }

        $activeHolds = $request->session()->get(self::ACTIVE_HOLDS_KEY, []);
        $activeHolds = is_array($activeHolds) ? $activeHolds : [];
        $activeHolds[(int) $booking->showtime_id] = (int) $booking->id;
        $request->session()->put(self::ACTIVE_HOLDS_KEY, array_slice($activeHolds, -20, null, true));
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
