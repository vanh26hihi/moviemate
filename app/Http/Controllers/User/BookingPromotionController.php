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
            throw ValidationException::withMessages([
                'discount_code' => 'Không thể áp dụng hoặc đổi mã khuyến mãi vì một giao dịch thanh toán đã được tạo cho đơn này. Để tránh sai lệch số tiền với cổng thanh toán, khuyến mãi phải được giữ nguyên. Hãy tiếp tục giao dịch đang chờ; nếu muốn dùng mã khác, hãy hủy đơn hiện tại và đặt lại.',
            ]);
        }
        $draft = $drafts->current($request, true);
        $code = $data['action'] === 'remove' ? null : mb_strtoupper(trim($data['code']));
        $preview = $previews->preview($draft);
        $promotions->quote($preview->prices->grandTotal, $code, $request->user()?->id, (int) $preview->showtime->cinema_id);
        $drafts->updatePromotionCode($request, $code);

        return back()->with('success', $data['action'] === 'remove' ? 'Đã gỡ khuyến mãi.' : 'Đã áp dụng khuyến mãi.');
    }
}
