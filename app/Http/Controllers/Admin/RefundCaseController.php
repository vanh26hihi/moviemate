<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveRefundCaseRequest;
use App\Models\RefundCase;
use App\Services\CinemaAccessService;
use App\Services\RefundCaseResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class RefundCaseController extends Controller
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    public function index(Request $request): View
    {
        $status = $request->string('status', RefundCase::STATUS_REQUIRED)->toString();
        abort_unless(in_array($status, [RefundCase::STATUS_REQUIRED, RefundCase::STATUS_RESOLVED, 'all'], true), 422);
        $query = RefundCase::query()->with([
            'cinema:id,code,name',
            'booking:id,showtime_id,booking_code,customer_name,customer_email,customer_phone',
            'booking.showtime:id,movie_id,show_date,show_time',
            'booking.showtime.movie:id,title',
            'payment:id,booking_id,provider,status,amount,currency,verified_at,settled_at',
            'cancellation:id,showtime_id,reason_code,cancelled_at,cancelled_by_user_id',
            'cancellation.cancelledBy:id,name',
            'resolvedBy:id,name',
        ]);
        $this->cinemaAccess->scope($query, $request->user(), 'refund_cases.cinema_id');
        $refundCases = $query
            ->when($status !== 'all', fn ($query) => $query->where('refund_cases.status', $status))
            ->orderByRaw("CASE WHEN refund_cases.status = 'required' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.refunds.index', compact('refundCases', 'status'));
    }

    public function update(ResolveRefundCaseRequest $request, RefundCase $refundCase, RefundCaseResolutionService $resolutions): RedirectResponse
    {
        $this->cinemaAccess->authorizeCinema($request->user(), (int) $refundCase->cinema_id);
        $wasResolved = $refundCase->status === RefundCase::STATUS_RESOLVED;
        $resolutions->resolve($refundCase, $request->user(), $request->validated());

        return redirect()->route('admin.refunds.index')
            ->with($wasResolved ? 'warning' : 'success', $wasResolved
                ? 'Nghĩa vụ này đã được ghi nhận hoàn tiền trước đó.'
                : 'Đã ghi nhận hoàn tiền thủ công. Payment gốc vẫn được giữ nguyên.');
    }
}
