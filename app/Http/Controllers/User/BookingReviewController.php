<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\BookingCheckoutDraftService;
use App\Services\BookingCheckoutPreviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingReviewController extends Controller
{
    public function __invoke(
        Request $request,
        BookingCheckoutDraftService $drafts,
        BookingCheckoutPreviewService $previews,
    ): View {
        $draft = $drafts->current($request, true);
        $preview = $previews->preview($draft);

        return view('user.bookings.review', compact('draft', 'preview'));
    }
}
