<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\BookingCheckoutDraftService;
use App\Services\BookingCheckoutPreviewService;
use App\Services\LoyaltyService;
use App\Services\Payments\PaymentInitiationService;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingReviewController extends Controller
{
    public function __invoke(
        Request $request,
        BookingCheckoutDraftService $drafts,
        BookingCheckoutPreviewService $previews,
        PaymentInitiationService $payments,
        PromotionService $promotions,
        LoyaltyService $loyaltyService,
    ): View {
        $draft = $drafts->current($request, true);
        $preview = $previews->preview($draft);
        $paymentProviders = $payments->availability();
        $promotion = $promotions->quote($preview->prices->grandTotal, $draft['discount_codes'] ?? [], $request->user()?->id, (int) $preview->showtime->cinema_id);
        $loyalty = $loyaltyService->quote($request->user()?->id, $promotion->finalAmount, (int) ($draft['points_to_use'] ?? 0));

        return view('user.bookings.review', compact('draft', 'preview', 'paymentProviders', 'promotion', 'loyalty'));
    }
}
