<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FoodPickupVoucher;
use App\Services\Tickets\BookingPrintAmountAllocator;
use App\Services\Tickets\FoodPickupVoucherPrintService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class FoodPickupVoucherPrintController extends Controller
{
    public function __invoke(Request $request, FoodPickupVoucher $foodPickupVoucher, FoodPickupVoucherPrintService $prints, BookingPrintAmountAllocator $amounts): Response
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        [$voucher, $printAmounts] = DB::transaction(function () use ($foodPickupVoucher, $request, $validated, $prints, $amounts): array {
            $voucher = $prints->record($foodPickupVoucher, $request->user(), $validated['reason'] ?? null);
            $voucher->load([
                'booking.showtime.cinema',
                'booking.showtime.movie',
                'booking.showtime.room',
                'booking.foodOrder.items',
                'lastPrintedBy:id,name',
            ]);

            return [$voucher, $amounts->allocate($voucher->booking)];
        }, 3);

        return response()->view('staff.tickets.food-voucher-print', [
            'voucher' => $voucher,
            'booking' => $voucher->booking,
            'allocatedAmount' => $printAmounts->foodVoucherAmount,
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')->header('Pragma', 'no-cache');
    }
}
