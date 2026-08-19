<?php

namespace App\Http\Controllers\User;

use App\Exceptions\PricingConfigurationException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\FoodItem;
use App\Models\Seat;
use App\Models\SeatIncidentResolution;
use App\Models\Showtime;
use App\Services\BookingCheckoutDraftService;
use App\Services\BookingCheckoutPreviewService;
use App\Services\BookingExpirationService;
use App\Services\GuestBookingAccessService;
use App\Services\Mail\TicketMailConfigurationInspector;
use App\Services\Payments\BookingPaymentActionPolicy;
use App\Services\PublicShowtimeCatalog;
use App\Services\RoomLayoutService;
use App\Services\Tickets\BookingQrPayload;
use App\Services\Tickets\BookingTicketEligibility;
use App\Support\SeatPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function __construct(
        private readonly RoomLayoutService $layouts,
        private readonly GuestBookingAccessService $guestAccess,
        private readonly BookingCheckoutDraftService $drafts,
        private readonly BookingCheckoutPreviewService $previews,
        private readonly BookingTicketEligibility $ticketEligibility,
        private readonly BookingQrPayload $bookingQrPayloads,
        private readonly TicketMailConfigurationInspector $mailConfiguration,
        private readonly PublicShowtimeCatalog $showtimeCatalog,
        private readonly BookingExpirationService $expiration,
        private readonly BookingPaymentActionPolicy $paymentActions,
    ) {}

    /**
     * Show seat selection page for a given showtime.
     */
    public function selectSeat(Request $request, Showtime $showtime)
    {
        $showtime->load(['movie', 'cinema', 'room', 'presentationFormat', 'roomLayout.cells.seat']);
        $this->assertExpectedCinema($request, $showtime);

        if (! $this->isShowtimeAvailable($showtime)) {
            return redirect()
                ->route('user.movies.show', $showtime->movie->slug)
                ->with('error', 'Suất chiếu này đã đóng nhận đặt vé.');
        }

        $layout = $this->layouts->resolveForShowtime($showtime);
        $this->expiration->expireStaleForShowtime($showtime->id);
        $layoutCells = $layout->cells->sortBy(fn ($cell) => sprintf('%03d:%03d', $cell->y_position, $cell->x_position))->values();
        $seats = $layoutCells->where('cell_type', 'seat')->pluck('seat')->filter()->values();
        try {
            $seatPrices = $this->showtimeCatalog->pricesFor($showtime);
        } catch (PricingConfigurationException $exception) {
            return redirect()->route('user.movies.show', $showtime->movie->slug)
                ->with('error', $exception->getMessage());
        }

        $bookedSeatIds = BookingSeat::query()
            ->where('showtime_id', $showtime->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->pluck('seat_id')
            ->all();

        $selectedSeatIds = [];
        if ($this->drafts->hasCurrent($request)) {
            $draft = $this->drafts->current($request);
            if ((int) $draft['showtime_id'] === (int) $showtime->id) {
                $selectedSeatIds = collect($draft['seat_ids'])
                    ->map(fn ($seatId): int => (int) $seatId)
                    ->filter(fn (int $seatId): bool => $seatId > 0)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return view('user.bookings.select-seat', compact(
            'showtime',
            'layout',
            'layoutCells',
            'seats',
            'bookedSeatIds',
            'selectedSeatIds',
            'seatPrices',
        ));
    }

    /**
     * Show checkout page for selected seats.
     */
    public function checkout(Request $request, Showtime $showtime)
    {
        $showtime->load(['movie', 'cinema', 'room', 'roomLayout']);
        $this->assertExpectedCinema($request, $showtime);

        if (! $this->isShowtimeAvailable($showtime)) {
            return redirect()
                ->route('user.movies.show', $showtime->movie->slug)
                ->with('error', 'Suất chiếu này đã đóng nhận đặt vé.');
        }

        $seatIds = $this->parseSeatIds($request->query('selected_seats', ''));

        if (empty($seatIds)) {
            return redirect()
                ->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Vui lòng chọn ít nhất một ghế.');
        }

        $layout = $this->layouts->resolveForShowtime($showtime);
        $this->expiration->expireStaleForShowtime($showtime->id);
        $seats = Seat::where('room_id', $showtime->room_id)
            ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id))
            ->whereIn('id', $seatIds)
            ->orderBy('row')
            ->orderBy('number')
            ->get();

        if ($seats->count() !== count($seatIds)) {
            return redirect()
                ->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Danh sách ghế không hợp lệ.');
        }

        if ($seats->contains(fn ($seat) => $seat->status !== 'active')) {
            return redirect()
                ->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Có ghế đang bảo trì hoặc không khả dụng.');
        }

        if (! $this->coupleSelectionIsComplete($seats, $layout->id)) {
            return redirect()->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Ghế đôi phải được chọn đủ cả cặp.');
        }

        $bookedSeatIds = BookingSeat::query()
            ->where('showtime_id', $showtime->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->whereIn('seat_id', $seatIds)
            ->pluck('seat_id')
            ->all();

        if (! empty($bookedSeatIds)) {
            return redirect()
                ->route('user.bookings.selectSeat', $showtime->id)
                ->with('error', 'Một số ghế bạn chọn đã được người khác đặt trước.');
        }

        $draft = $this->drafts->start($request, $showtime->id, $seatIds);
        try {
            $preview = $this->previews->preview($draft);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('user.bookings.selectSeat', $showtime)
                ->withErrors($exception->errors());
        }
        $foods = FoodItem::query()->where('active', true)->orderBy('name')->get();

        return view('user.bookings.food', compact('draft', 'preview', 'foods'));
    }

    /**
     * Show booking success page.
     */
    public function success(Request $request, Booking $booking)
    {
        $this->authorizeBookingView($request, $booking);
        $this->expiration->expire($booking->id);
        $booking->refresh();
        abort_unless($booking->user_id === Auth::id(), 403);

        $booking->load([
            'user',
            'payment',
            'payments',
            'ticketDelivery',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
            'showtime.presentationFormat',
            'bookingSeats.seat',
            'admissionTickets.bookingSeat.seat',
            'foodOrder.items',
            'foodPickupVoucher',
        ]);
        $this->loadCancellationContext($booking);

        $isUsable = $this->ticketEligibility->isUsable($booking);
        $verifiedPayment = $this->ticketEligibility->verifiedPayment($booking);
        $mailDeliveryReady = $this->mailConfiguration->inspect()['ready'];
        $paymentAction = $this->paymentActions->evaluate($booking);

        return view('user.bookings.success', compact(
            'booking',
            'isUsable',
            'verifiedPayment',
            'mailDeliveryReady',
            'paymentAction',
        ));

    }

    public function ticket(Request $request, Booking $booking)
    {
        $this->authorizeBookingView($request, $booking);

        $booking->load([
            'user',
            'payment',
            'payments',
            'showtime.movie',
            'showtime.cinema',
            'showtime.room',
            'showtime.presentationFormat',
            'bookingSeats.seat',
            'admissionTickets.bookingSeat.seat',
            'foodOrder.items',
            'foodPickupVoucher',
        ]);
        $this->loadCancellationContext($booking);

        $isUsable = $this->ticketEligibility->isUsable($booking);
        $isDeliverable = $this->ticketEligibility->isDeliverable($booking);
        $verifiedPayment = $this->ticketEligibility->verifiedPayment($booking);
        $bookingQrPayload = $isDeliverable ? $this->bookingQrPayloads->value($booking) : null;
        $ticketState = match (true) {
            $booking->payment_status === 'refunded' => 'refunded',
            $booking->booking_status === 'cancelled' => 'cancelled',
            $booking->booking_status === 'expired' => 'expired',
            $isUsable => 'valid',
            default => 'invalid',
        };
        $relocations = SeatIncidentResolution::query()
            ->whereIn('resolution_type', [SeatIncidentResolution::TYPE_EQUIVALENT, SeatIncidentResolution::TYPE_UPGRADE])
            ->whereHas('impact.bookingSeat', fn ($query) => $query->where('booking_id', $booking->id))
            ->with(['originalSeat:id,seat_code', 'replacementSeat:id,seat_code'])
            ->oldest('id')->get();

        return response()->view('user.bookings.ticket', compact(
            'booking', 'isUsable', 'isDeliverable', 'verifiedPayment', 'bookingQrPayload', 'ticketState', 'relocations'
        ))->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    private function authorizeBookingView(Request $request, Booking $booking): void
    {
        if (Auth::check()) {
            Gate::authorize('view', $booking);

            return;
        }

        abort_unless(
            $this->guestAccess->allows($request, $booking),
            404,
        );
    }

    private function loadCancellationContext(Booking $booking): void
    {
        $booking->setRelation('showtimeCancellationImpact', null);
        $booking->setRelation('refundCase', null);
        if ($booking->booking_status === 'cancelled') {
            $booking->load(['showtimeCancellationImpact.cancellation', 'refundCase']);
        }
    }

    /**
     * Parse seat ids from comma-separated query string.
     */
    protected function parseSeatIds(string $selectedSeats): array
    {
        return collect(explode(',', $selectedSeats))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => $id !== '' && ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Check whether a showtime can still be booked.
     */
    protected function isShowtimeAvailable(Showtime $showtime): bool
    {
        return $this->showtimeCatalog->isCustomerSellable($showtime);
    }

    private function assertExpectedCinema(Request $request, Showtime $showtime): void
    {
        if ($request->query->has('cinema')) {
            abort_unless(
                hash_equals((string) $showtime->cinema->code, mb_strtoupper((string) $request->query('cinema'))),
                404,
            );
        }
        if ($request->query->has('cinema_id')) {
            abort_unless($request->integer('cinema_id') === (int) $showtime->cinema_id, 404);
        }
    }

    private function coupleSelectionIsComplete($seats, int $layoutId): bool
    {
        $couples = $seats->where('type', 'couple')->groupBy('pair_code');
        foreach ($couples as $pairCode => $selectedPair) {
            if (! $pairCode || $selectedPair->count() !== 2
                || $selectedPair->pluck('pair_position')->sort()->values()->all() !== ['left', 'right']
                || ! SeatPresentation::isValidCouple($selectedPair)) {
                return false;
            }

            $layoutPairCount = Seat::query()
                ->where('room_id', $selectedPair->first()->room_id)
                ->where('type', 'couple')
                ->where('pair_code', $pairCode)
                ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layoutId))
                ->count();
            if ($layoutPairCount !== 2) {
                return false;
            }
        }

        return true;
    }
}
