<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\BookingCheckoutDraftService;
use App\Services\BookingCheckoutPreviewService;
use App\Services\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BookingPromotionController extends Controller
{
    public function __invoke(Request $request, BookingCheckoutDraftService $drafts, BookingCheckoutPreviewService $previews, PromotionService $promotions): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['apply', 'remove'])], 'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/']]);
        if ($drafts->promotionsAreLocked($request)) {
            throw ValidationException::withMessages(['discount_code' => 'Mã giảm giá đã được khóa cho lần thanh toán hiện tại.']);
        }
        $draft = $drafts->current($request, true);
        $code = $data['action'] === 'remove' ? null : mb_strtoupper(trim($data['code']));
        $preview = $previews->preview($draft);
        $promotions->quote($preview->prices->grandTotal, $code, $request->user()?->id, (int) $preview->showtime->cinema_id);
        $drafts->updatePromotionCode($request, $code);

        return back()->with('success', $data['action'] === 'remove' ? 'Đã gỡ mã giảm giá.' : 'Đã áp dụng mã giảm giá.');
    }
}
