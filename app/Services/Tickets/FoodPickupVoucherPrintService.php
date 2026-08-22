<?php

namespace App\Services\Tickets;

use App\Models\FoodPickupVoucher;
use App\Models\FoodPickupVoucherPrintEvent;
use App\Models\User;
use App\Services\CinemaAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class FoodPickupVoucherPrintService
{
    public function __construct(
        private readonly BookingTicketEligibility $eligibility,
        private readonly CinemaAccessService $cinemaAccess,
    ) {}

    public function record(FoodPickupVoucher $voucher, User $actor, ?string $reason): FoodPickupVoucher
    {
        return DB::transaction(function () use ($voucher, $actor, $reason): FoodPickupVoucher {
            $voucher = FoodPickupVoucher::query()->with(['booking.payments', 'booking.foodOrder.items'])
                ->lockForUpdate()->findOrFail($voucher->id);
            $booking = $voucher->booking;
            abort_unless($booking->cinema_id
                && $this->cinemaAccess->allowsInCurrentContext($actor, (int) $booking->cinema_id), 404);
            if (! $this->eligibility->isPrintable($booking) || $booking->foodOrder?->items->isEmpty() !== false) {
                throw new HttpException(409, 'Phiếu nhận đồ ăn không còn đủ điều kiện in.');
            }

            $safeReason = $this->safeReason($reason);
            if ($voucher->print_count > 0 && $safeReason === null) {
                throw new HttpException(422, 'Vui lòng nhập lý do in lại phiếu nhận đồ ăn.');
            }

            $printedAt = now();
            $printNumber = $voucher->print_count + 1;
            $voucher->forceFill([
                'print_count' => $printNumber,
                'last_printed_at' => $printedAt,
                'last_printed_by_user_id' => $actor->id,
            ])->save();
            FoodPickupVoucherPrintEvent::query()->create([
                'food_pickup_voucher_id' => $voucher->id,
                'actor_user_id' => $actor->id,
                'print_number' => $printNumber,
                'reason' => $safeReason,
                'printed_at' => $printedAt,
            ]);

            return $voucher;
        }, 3);
    }

    private function safeReason(?string $reason): ?string
    {
        $safe = $reason === null ? '' : Str::limit(strip_tags(trim($reason)), 300, '');

        return $safe === '' ? null : $safe;
    }
}
