<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\BookingTicketPrint;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\TicketCheckinEvent;
use App\Services\CinemaAccessService;
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
        $stats = ['sold' => 0, 'checked_in' => 0, 'waiting_print' => 0, 'print_attention' => 0, 'pending_counter' => 0];
        $showtimes = collect();

        if ($cinema) {
            $base = Booking::query()->where('cinema_id', $cinema->id);
            $stats['sold'] = (clone $base)->whereBetween('paid_at', $day)->whereIn('booking_status', ['paid', 'used'])->count();
            $stats['checked_in'] = TicketCheckinEvent::query()->where('result', TicketCheckinEvent::RESULT_ACCEPTED)
                ->whereBetween('scanned_at', $day)->whereHas('booking', fn (Builder $query) => $query->where('cinema_id', $cinema->id))->count();
            $stats['waiting_print'] = (clone $base)->where('payment_status', 'paid')->where('booking_status', 'paid')
                ->whereDoesntHave('ticketPrint')->count();
            $stats['print_attention'] = BookingTicketPrint::query()
                ->whereIn('status', [BookingTicketPrint::STATUS_RETRY_ALLOWED, BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION])
                ->whereHas('booking', fn (Builder $query) => $query->where('cinema_id', $cinema->id))->count();
            $stats['pending_counter'] = (clone $base)->where('sales_channel', Booking::SALES_CHANNEL_COUNTER)
                ->where('created_by_staff_id', $request->user()->id)->where('booking_status', 'pending_payment')
                ->where('expires_at', '>', now())->count();

            $showtimes = Showtime::query()->where('cinema_id', $cinema->id)
                ->where('status', 'active')
                ->whereDate('show_date', $now->toDateString())
                ->with(['movie:id,title,duration,age_rating', 'room:id,name,room_type'])
                ->select('showtimes.*')
                ->selectSub(RoomLayoutCell::query()->selectRaw('COUNT(*)')
                    ->join('seats', 'seats.id', '=', 'room_layout_cells.seat_id')
                    ->whereColumn('room_layout_cells.room_layout_id', 'showtimes.room_layout_id')
                    ->where('room_layout_cells.cell_type', 'seat')->where('seats.status', Seat::STATUS_ACTIVE), 'operational_seats_count')
                ->selectSub(BookingSeat::query()->selectRaw('COUNT(*)')
                    ->whereColumn('booking_seats.showtime_id', 'showtimes.id')
                    ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY), 'sold_seats_count')
                ->orderBy('show_time')->get();
        }

        return view('staff.dashboard', compact('cinema', 'now', 'stats', 'showtimes'));
    }

    public function sales(Request $request, CinemaAccessService $cinemas): View
    {
        $cinema = $cinemas->currentCinema($request->user());
        $timezone = $cinema?->timezone ?: config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:pending_payment,paid,used,cancelled,expired'],
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
                    'authoritativePayment.settledBy:id,name', 'ticketPrint', 'acceptedTicketCheckin'])
                ->latest('id')->paginate(20)->withQueryString();
        }

        return view('staff.sales.index', compact('cinema', 'date', 'bookings'));
    }

    public function checkins(Request $request, CinemaAccessService $cinemas): View
    {
        $cinema = $cinemas->currentCinema($request->user());
        $events = TicketCheckinEvent::query()->whereRaw('1 = 0')->paginate(20);
        if ($cinema) {
            $events = TicketCheckinEvent::query()->where('result', TicketCheckinEvent::RESULT_ACCEPTED)
                ->whereHas('booking', fn (Builder $query) => $query->where('cinema_id', $cinema->id))
                ->with(['actor:id,name', 'booking.showtime.movie:id,title', 'booking.showtime.room:id,name', 'booking.bookingSeats.seat'])
                ->latest('scanned_at')->paginate(20);
        }

        return view('staff.checkins.index', compact('cinema', 'events'));
    }

    public function prints(Request $request, CinemaAccessService $cinemas): View
    {
        $cinema = $cinemas->currentCinema($request->user());
        $bookings = Booking::query()->whereRaw('1 = 0')->paginate(20);
        if ($cinema) {
            $bookings = Booking::query()->where('cinema_id', $cinema->id)
                ->where('payment_status', 'paid')->where('booking_status', 'paid')
                ->where(function (Builder $query): void {
                    $query->whereDoesntHave('ticketPrint')->orWhereHas('ticketPrint', fn (Builder $print) => $print
                        ->whereIn('status', [BookingTicketPrint::STATUS_RETRY_ALLOWED, BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION, BookingTicketPrint::STATUS_RETRY_AUTHORIZED]));
                })->with(['showtime.movie:id,title', 'showtime.room:id,name', 'bookingSeats.seat', 'ticketPrint.lastFailedBy:id,name'])
                ->oldest('paid_at')->paginate(20);
        }

        return view('staff.prints.index', compact('cinema', 'bookings'));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function dayWindow(CarbonImmutable $date): array
    {
        return [$date->startOfDay()->utc(), $date->endOfDay()->utc()];
    }
}
