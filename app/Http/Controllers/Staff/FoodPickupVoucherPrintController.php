<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FoodPickupVoucher;
use App\Services\Tickets\FoodPickupVoucherPrintService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class FoodPickupVoucherPrintController extends Controller
{
    public function __invoke(Request $request, FoodPickupVoucher $foodPickupVoucher, FoodPickupVoucherPrintService $prints): Response
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $voucher = $prints->record($foodPickupVoucher, $request->user(), $validated['reason'] ?? null);
        $voucher->load(['booking.showtime.cinema', 'booking.foodOrder.items', 'lastPrintedBy:id,name']);

        return response()->view('staff.tickets.food-voucher-print', [
            'voucher' => $voucher,
            'booking' => $voucher->booking,
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')->header('Pragma', 'no-cache');
    }
}
