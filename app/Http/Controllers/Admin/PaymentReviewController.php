<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentReviewResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

class PaymentReviewController extends Controller
{
    public function index(): View
    {
        $payments = Payment::query()
            ->with('booking')
            ->where('status', Payment::STATUS_REVIEW)
            ->latest('id')
            ->paginate(25);

        return view('admin.payment-reviews.index', compact('payments'));
    }

    public function resolve(
        Request $request,
        string $paymentId,
        PaymentReviewResolutionService $reviews,
    ): RedirectResponse {
        $payment = Payment::query()->findOrFail($paymentId);

        if ($payment->status !== Payment::STATUS_REVIEW) {
            return redirect()->route('admin.payment-reviews.index')
                ->with('payment_review_error', 'Only payments currently in review may be reconciled manually.');
        }

        try {
            $result = $reviews->resolve($payment, $request->user());
        } catch (LogicException $exception) {
            return redirect()->route('admin.payment-reviews.index')
                ->with('payment_review_error', $exception->getMessage());
        }

        return redirect()->route('admin.payment-reviews.index')
            ->with('payment_review_result', $result->message);
    }
}
