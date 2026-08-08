<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\BookingCheckoutDraftService;
use App\Services\BookingCheckoutPreviewService;
use App\Services\LoyaltyService;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BookingLoyaltyController extends Controller
{
    public function __invoke(Request $request, BookingCheckoutDraftService $drafts, BookingCheckoutPreviewService $previews, PromotionService $promotions, LoyaltyService $loyalty): RedirectResponse
    {
        $points = $request->validate(['points' => ['required', 'integer', 'min:0']])['points'];
        $draft = $drafts->current($request, true);
        $preview = $previews->preview($draft);
        $promotion = $promotions->quote($preview->prices->grandTotal, $draft['discount_codes'] ?? [], $request->user()?->id, (int) $preview->showtime->cinema_id);
        $loyalty->quote($request->user()?->id, $promotion->finalAmount, $points);
        $drafts->updatePoints($request, $points);

        return back()->with('success', 'Đã cập nhật điểm sử dụng.');
    }
}
