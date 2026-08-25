<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketPrint;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\SeatIncidentResolution;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use App\Services\Tickets\BookingTicketEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WorkspaceController extends Controller
{
    public function dashboard(Request $request, CinemaAccessService $cinemas): View
    {
        $cinema = $cinemas->currentCinema($request->user());
        $timezone = $cinema?->timezone ?: config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $now = CarbonImmutable::now($timezone);
        $day = $this->dayWindow($now);
        $stats = [
            'paid_bookings_today' => 0,
            'tickets_sold_today' => 0,
            'waiting_print' => 0,
            'print_attention' => 0,
            'pending_counter' => 0,
        ];
        $attentionItems = collect();
        $showtimes = collect();
        $completedShowtimes = collect();

        if ($cinema) {
            $base = Booking::query()->where('cinema_id', $cinema->id);
            $paidToday = (clone $base)->whereBetween('paid_at', $day)
                ->where('payment_status', 'paid')->where('booking_status', 'paid')
                ->whereHas('authoritativePayment');
            $stats['paid_bookings_today'] = (clone $paidToday)->count();
            $stats['tickets_sold_today'] = AdmissionTicket::query()
                ->whereHas('booking', fn (Builder $query) => $query->where('cinema_id', $cinema->id)
                    ->whereBetween('paid_at', $day)->where('payment_status', 'paid')->where('booking_status', 'paid')
                    ->whereHas('authoritativePayment'))
                ->count();
            $stats['waiting_print'] = AdmissionTicket::query()->where('print_count', 0)
                ->whereHas('booking', fn (Builder $query) => $query->where('cinema_id', $cinema->id)
                    ->where('payment_status', 'paid')->where('booking_status', 'paid')
                    ->whereHas('authoritativePayment'))->count();
            $stats['print_attention'] = BookingTicketPrint::query()
                ->whereIn('status', [BookingTicketPrint::STATUS_RETRY_ALLOWED, BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION])
                ->whereHas('booking', fn (Builder $query) => $query->where('cinema_id', $cinema->id)
                    ->where('payment_status', 'paid')->where('booking_status', 'paid')
                    ->whereHas('authoritativePayment'))->count();
            $stats['pending_counter'] = (clone $base)->where('sales_channel', Booking::SALES_CHANNEL_COUNTER)
                ->where('created_by_staff_id', $request->user()->id)->where('booking_status', 'pending_payment')
                ->where('expires_at', '>', $now->utc())->count();

            $pendingCounterBookings = (clone $base)
                ->where('sales_channel', Booking::SALES_CHANNEL_COUNTER)
                ->where('created_by_staff_id', $request->user()->id)
                ->where('booking_status', 'pending_payment')
                ->where('expires_at', '>', $now->utc())
                ->with([
                    'payment:payments.id,payments.booking_id,payments.provider,payments.status',
                    'showtime:id,movie_id,room_id,show_date,show_time',
                    'showtime.movie:id,title',
                    'showtime.room:id,name',
                    'bookingSeats.seat:id,row,number,seat_code,type,pair_code,pair_position',
                ])
                ->oldest('expires_at')->limit(3)->get();

            $printAttentionBookings = (clone $base)
                ->where('payment_status', 'paid')->where('booking_status', 'paid')
                ->whereHas('authoritativePayment')
                ->whereHas('admissionTickets.printState', fn (Builder $query) => $query->whereIn('status', [
                    BookingTicketPrint::STATUS_RETRY_ALLOWED,
                    BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION,
                ]))
                ->withCount(['admissionTickets as attention_ticket_count' => fn (Builder $query) => $query
                    ->whereHas('printState', fn (Builder $print) => $print->whereIn('status', [
                        BookingTicketPrint::STATUS_RETRY_ALLOWED,
                        BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION,
                    ]))])
                ->with(['showtime.movie:id,title'])
                ->oldest('paid_at')->limit(3)->get();

            $printAttentionBookingIds = $printAttentionBookings->pluck('id');
            $unprintedBookings = (clone $base)
                ->where('payment_status', 'paid')->where('booking_status', 'paid')
                ->whereHas('authoritativePayment')
                ->whereHas('admissionTickets', fn (Builder $query) => $query->where('print_count', 0))
                ->when($printAttentionBookingIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $printAttentionBookingIds))
                ->withCount(['admissionTickets as unprinted_ticket_count' => fn (Builder $query) => $query->where('print_count', 0)])
                ->with(['showtime.movie:id,title'])
                ->oldest('paid_at')->limit(3)->get();

            $attentionItems = $pendingCounterBookings->map(fn (Booking $booking): array => [
                'type' => 'payment',
                'booking' => $booking,
                'count' => null,
                'expires_at' => $booking->expires_at?->toImmutable()->timezone($timezone),
                'payment_provider' => $booking->payment?->provider,
                'payment_status' => $booking->payment?->status,
            ])->concat($printAttentionBookings->map(fn (Booking $booking): array => [
                'type' => 'print_attention',
                'booking' => $booking,
                'count' => (int) $booking->attention_ticket_count,
                'expires_at' => null,
                'payment_provider' => null,
                'payment_status' => null,
            ]))->concat($unprintedBookings->map(fn (Booking $booking): array => [
                'type' => 'unprinted',
                'booking' => $booking,
                'count' => (int) $booking->unprinted_ticket_count,
                'expires_at' => null,
                'payment_provider' => null,
                'payment_status' => null,
            ]))->take(5)->values();

            $showtimes = Showtime::query()->where('cinema_id', $cinema->id)
                ->where('status', 'active')
                ->whereDate('show_date', $now->toDateString())
                ->with([
                    'movie:id,title,duration,age_rating',
                    'room:id,name,room_type,room_type_id',
                    'room.roomType:id,code,name',
                    'presentationFormat:id,name',
                ])
                ->select('showtimes.*')
                ->selectSub(RoomLayoutCell::query()->selectRaw('COUNT(*)')
                    ->join('seats', 'seats.id', '=', 'room_layout_cells.seat_id')
                    ->whereColumn('room_layout_cells.room_layout_id', 'showtimes.room_layout_id')
                    ->where('room_layout_cells.cell_type', 'seat')->where('seats.status', Seat::STATUS_ACTIVE), 'operational_seats_count')
                ->selectSub(BookingSeat::query()->selectRaw('COUNT(*)')
                    ->whereColumn('booking_seats.showtime_id', 'showtimes.id')
                    ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY), 'sold_seats_count')
                ->orderBy('show_time')->get();

            $completedShowtimes = $showtimes->filter(function (Showtime $showtime) use ($cinema, $now): bool {
                $startsAt = CarbonImmutable::parse(
                    $showtime->show_date->format('Y-m-d').' '.$showtime->show_time,
                    $cinema->timezone ?: config('cinema.timezone'),
                );

                return $now->greaterThanOrEqualTo($startsAt->addMinutes((int) ($showtime->movie->duration ?: 90)));
            })->values();
            $showtimes = $showtimes->reject(fn (Showtime $showtime): bool => $completedShowtimes->contains('id', $showtime->id))->values();
        }

        return view('staff.dashboard', compact('cinema', 'now', 'stats', 'attentionItems', 'showtimes', 'completedShowtimes'));
    }

    public function sales(
        Request $request,
        CinemaAccessService $cinemas,
        BookingTicketEligibility $ticketEligibility,
    ): View {
        $cinema = $cinemas->currentCinema($request->user());
        $timezone = $cinema?->timezone ?: config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:pending_payment,paid,cancelled,expired'],
            'channel' => ['nullable', 'in:counter,online'],
        ]);
        $date = CarbonImmutable::parse($validated['date'] ?? 'today', $timezone);
        abort_if(abs($date->diffInDays(CarbonImmutable::today($timezone), false)) > 31, 422, 'Ngày tra cứu nằm ngoài phạm vi vận hành.');
        $bookings = Booking::query()->whereRaw('1 = 0')->paginate(20);

        if ($cinema) {
            $bookings = Booking::query()->where('cinema_id', $cinema->id)
                ->whereBetween('created_at', $this->dayWindow($date))
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('booking_status', $status))
                ->when($validated['channel'] ?? null, fn (Builder $query, string $channel) => $query->where('sales_channel', $channel))
                ->with(['showtime.movie:id,title', 'showtime.room:id,name', 'bookingSeats.seat', 'createdByStaff:id,name',
                    'payments', 'authoritativePayment.settledBy:id,name', 'admissionTickets.printState'])
                ->latest('id')->paginate(20)->withQueryString();
        }

        $bookings->getCollection()->each(
            fn (Booking $booking) => $booking->setAttribute('can_print', $ticketEligibility->isPrintable($booking)),
        );

        return view('staff.sales.index', compact('cinema', 'date', 'bookings'));
    }

    public function prints(Request $request, CinemaAccessService $cinemas): View
    {
        $cinema = $cinemas->currentCinema($request->user());
        $bookings = Booking::query()->whereRaw('1 = 0')->paginate(20);
        if ($cinema) {
            $bookings = Booking::query()->where('cinema_id', $cinema->id)
                ->where('payment_status', 'paid')->where('booking_status', 'paid')
                ->where(function (Builder $eligible): void {
                    $eligible->whereHas('admissionTickets', function (Builder $tickets): void {
                        $tickets->where('print_count', 0)->orWhereHas('printState', fn (Builder $print) => $print
                            ->whereIn('status', [BookingTicketPrint::STATUS_RETRY_ALLOWED, BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION, BookingTicketPrint::STATUS_RETRY_AUTHORIZED]));
                    })->orWhereExists(fn ($query) => $query->selectRaw('1')
                        ->from('seat_incident_resolutions')
                        ->join('seat_incident_impacts', 'seat_incident_impacts.id', '=', 'seat_incident_resolutions.seat_incident_impact_id')
                        ->join('booking_seats', 'booking_seats.id', '=', 'seat_incident_impacts.booking_seat_id')
                        ->join('seat_incidents', 'seat_incidents.id', '=', 'seat_incident_impacts.seat_incident_id')
                        ->whereColumn('booking_seats.booking_id', 'bookings.id')
                        ->where('seat_incident_resolutions.reprint_required', true)
                        ->whereNull('seat_incident_resolutions.reprint_satisfied_at')
                        ->where('seat_incident_impacts.resolution_status', 'unresolved')
                        ->where('seat_incidents.status', 'open'));
                })->with(['showtime.movie:id,title', 'showtime.room:id,name', 'bookingSeats.seat',
                    'admissionTickets.bookingSeat.seat', 'admissionTickets.printState.lastFailedBy:id,name'])
                ->oldest('paid_at')->paginate(20);
        }
        $incidentReprintSeatIds = SeatIncidentResolution::query()
            ->where('reprint_required', true)->whereNull('reprint_satisfied_at')
            ->whereHas('impact.bookingSeat', fn ($query) => $query->whereIn('booking_id', $bookings->pluck('id')))
            ->whereHas('impact.incident', fn ($query) => $query->where('status', 'open'))
            ->with('impact:id,booking_seat_id')->get()->pluck('impact.booking_seat_id')->map(fn ($id): int => (int) $id)->flip();

        return view('staff.prints.index', compact('cinema', 'bookings', 'incidentReprintSeatIds'));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function dayWindow(CarbonImmutable $date): array
    {
        return [$date->startOfDay()->utc(), $date->endOfDay()->utc()];
    }
}
