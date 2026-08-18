<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Tickets\BookingPrintAmountAllocator;
use App\Services\Tickets\BookingTicketEligibility;
use App\Services\Tickets\FoodPickupVoucherPrintService;
use App\Services\Tickets\TicketPrintService;
use App\Services\Tickets\TicketResolutionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PrintAllController extends Controller
{
    public function __invoke(
        Request $request,
        Booking $booking,
        TicketResolutionService $tickets,
        TicketPrintService $ticketPrints,
        FoodPickupVoucherPrintService $voucherPrints,
        BookingPrintAmountAllocator $amounts,
        BookingTicketEligibility $eligibility,
    ): Response {
        $booking = $tickets->authorizedBooking($booking, $request->user());
        if (! $eligibility->isPrintable($booking)) {
            throw new HttpException(409, 'Chỉ đơn đã thanh toán với bằng chứng quyết toán hợp lệ mới được in.');
        }

        [$booking, $printAmounts] = DB::transaction(function () use ($booking, $request, $ticketPrints, $voucherPrints, $amounts, $eligibility): array {
            $booking = Booking::query()
                ->with(['payments', 'admissionTickets.printState', 'foodPickupVoucher', 'foodOrder.items'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if (! $eligibility->isPrintable($booking)) {
                throw new HttpException(409, 'Trạng thái đơn đã thay đổi; không thể phát hành tài liệu in.');
            }

            if ($booking->admissionTickets->isEmpty()
                || $booking->admissionTickets->contains(fn ($ticket) => $ticket->printState !== null || $ticket->print_count > 0)
                || ($booking->foodPickupVoucher && $booking->foodPickupVoucher->print_count > 0)) {
                throw new HttpException(409, 'In toàn bộ chỉ dùng cho lần in đầu. Hãy in lại từng tài liệu và cung cấp lý do.');
            }

            $printAmounts = $amounts->allocate($booking);

            foreach ($booking->admissionTickets as $ticket) {
                $operationId = (string) Str::uuid();
                $token = Str::random(64);
                $ticketPrints->start($ticket, $request->user(), $operationId, $token);
                $ticketPrints->succeed($ticket, $request->user(), $operationId, $token);
            }

            if ($booking->foodPickupVoucher) {
                $voucherPrints->record($booking->foodPickupVoucher, $request->user(), null);
            }

            return [$booking->fresh([
                'showtime.movie',
                'showtime.cinema',
                'showtime.room',
                'showtime.presentationFormat',
                'bookingSeats.seat',
                'admissionTickets.bookingSeat.seat',
                'admissionTickets.lastPrintedBy:id,name',
                'foodOrder.items',
                'foodPickupVoucher.lastPrintedBy:id,name',
            ]), $printAmounts];
        }, 3);

        return response()->view('staff.tickets.print-all', [
            'booking' => $booking,
            'printAmounts' => $printAmounts,
        ])
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }
}
