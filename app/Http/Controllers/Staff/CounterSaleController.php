<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\FoodSelectionValidationException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FoodItem;
use App\Models\Showtime;
use App\Services\BookingExpirationService;
use App\Services\BookingTokenService;
use App\Services\CinemaAccessService;
use App\Services\Counter\CounterBookingService;
use App\Services\Counter\CounterCashPaymentService;
use App\Services\PublicShowtimeCatalog;
use App\Services\RoomLayoutService;
use App\Services\Seats\SeatAvailabilitySnapshot;
use App\Services\TicketPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CounterSaleController extends Controller
{
    public function index(Request $request, CinemaAccessService $cinemas, PublicShowtimeCatalog $catalog): View
    {
        $actor = $request->user();
        $accessible = $cinemas->accessibleCinemas($actor);
        $cinema = $cinemas->currentCinema($actor);
        $showtimes = collect();

        if ($cinema || $cinemas->hasGlobalAccess($actor)) {
            $timezone = $cinema?->timezone ?: config('cinema.timezone', 'Asia/Ho_Chi_Minh');
            $from = CarbonImmutable::today($timezone);
            $to = $from->addDays(PublicShowtimeCatalog::WINDOW_DAYS - 1);
            $showtimes = $cinema
                ? $catalog->between($from->toDateString(), $to->toDateString(), $cinema)
                : $catalog->betweenForCinemas($accessible->pluck('id')->map(fn ($id) => (int) $id)->all(), $from->toDateString(), $to->toDateString());
        }

        return view('staff.counter.index', compact('accessible', 'cinema', 'showtimes'));
    }

    public function selectCinema(Request $request, CinemaAccessService $cinemas): RedirectResponse
    {
        $validated = $request->validate(['cinema_id' => ['required', 'integer', 'min:1']]);
        $cinemas->select($request->user(), (int) $validated['cinema_id']);

        return redirect()->route('staff.counter.index')->with('success', 'Đã chọn chi nhánh bán vé.');
    }

    public function seats(
        Request $request,
        Showtime $showtime,
        CinemaAccessService $cinemas,
        PublicShowtimeCatalog $catalog,
        RoomLayoutService $layouts,
        TicketPricingService $pricing,
        BookingTokenService $tokens,
        BookingExpirationService $expiration,
    ): View {
        $cinemas->authorizeCinema($request->user(), (int) $showtime->cinema_id);
        abort_unless($catalog->isSellable($showtime), 404);
        $layout = $layouts->resolveForShowtime($showtime)->load('cells.seat');
        $expiration->expireStaleForShowtime($showtime->id);
        $snapshot = SeatAvailabilitySnapshot::for($showtime, $layout);
        $seatPrices = $pricing->calculateSeatTypes($showtime, allowLegacySnapshot: false);
        $checkoutToken = $tokens->issueCheckoutToken();
        $showtime->loadMissing(['movie', 'cinema', 'room']);

        return view('staff.counter.seats', [
            'showtime' => $showtime,
            'layout' => $layout,
            'layoutCells' => $snapshot->cells,
            'unavailableSeatIds' => $snapshot->unavailableSeatIds,
            'seatPrices' => $seatPrices,
            'checkoutToken' => $checkoutToken,
        ]);
    }

    public function hold(
        Request $request,
        Showtime $showtime,
        CounterBookingService $counter,
    ): RedirectResponse {
        $request->merge(['customer_phone' => $this->normalizePhone($request->input('customer_phone'))]);
        $validated = $request->validate([
            'seat_ids' => ['required', 'array', 'min:1', 'max:50'],
            'seat_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'checkout_token' => ['required', 'string', 'max:200'],
            'customer_name' => ['nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
            'customer_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9]{8,15}$/'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'sales_channel' => ['prohibited'],
            'created_by_staff_id' => ['prohibited'],
            'staff_id' => ['prohibited'],
            'total_amount' => ['prohibited'],
            'seat_subtotal' => ['prohibited'],
        ]);

        $result = $counter->createHold(
            $request->user(), $showtime, $validated['seat_ids'], $validated['checkout_token'],
            $validated['customer_name'] ?? null, $validated['customer_phone'] ?? null,
            $validated['customer_email'] ?? null,
        );

        return redirect()->route('staff.counter.food', $result->booking)
            ->with('success', 'Đã giữ ghế cho đơn tại quầy.');
    }

    public function food(Request $request, Booking $booking, CounterBookingService $counter): View
    {
        $booking = $counter->authorized($request->user(), $booking);
        abort_unless($booking->booking_status === 'pending_payment' && $booking->payment_status === 'unpaid', 409);
        $foods = FoodItem::query()->where('active', true)
            ->where(fn ($query) => $query->whereNull('cinema_id')->orWhere('cinema_id', $booking->cinema_id))
            ->orderBy('name')->get();

        return view('staff.counter.food', compact('booking', 'foods'));
    }

    public function updateFood(Request $request, Booking $booking, CounterBookingService $counter): RedirectResponse
    {
        $validated = $request->validate([
            'food_items' => ['sometimes', 'array', 'max:100'],
            'food_items.*.food_id' => ['required', 'integer', 'distinct', 'min:1'],
            'food_items.*.quantity' => ['required', 'integer', 'min:0', 'max:20'],
            'food_subtotal' => ['prohibited'],
            'total_amount' => ['prohibited'],
        ]);
        try {
            $counter->updateFood($request->user(), $booking, $validated['food_items'] ?? []);
        } catch (FoodSelectionValidationException $exception) {
            throw ValidationException::withMessages(['food_items' => $exception->getMessage()]);
        }

        return redirect()->route('staff.counter.review', $booking)->with('success', 'Đã cập nhật đồ ăn.');
    }

    public function review(Request $request, Booking $booking, CounterBookingService $counter): View
    {
        $booking = $counter->authorized($request->user(), $booking);

        return view('staff.counter.review', compact('booking'));
    }

    public function cash(
        Request $request,
        Booking $booking,
        CounterCashPaymentService $payments,
    ): RedirectResponse {
        $request->validate([
            'amount' => ['prohibited'],
            'settled_by_user_id' => ['prohibited'],
            'received_by_user_id' => ['prohibited'],
            'staff_id' => ['prohibited'],
            'sales_channel' => ['prohibited'],
        ]);
        $payments->settle($booking, $request->user());

        return redirect()->route('staff.counter.review', $booking)
            ->with('success', 'Đã xác nhận thu tiền mặt.');
    }

    public function cancel(Request $request, Booking $booking, CounterBookingService $counter): RedirectResponse
    {
        $request->validate([
            'staff_id' => ['prohibited'],
            'created_by_staff_id' => ['prohibited'],
        ]);
        $counter->cancel($request->user(), $booking);

        return redirect()->route('staff.counter.index')
            ->with('success', 'Đã hủy đơn tại quầy và giải phóng ghế.');
    }

    private function normalizePhone(mixed $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        return preg_replace('/[\s().-]+/', '', trim($phone));
    }
}
