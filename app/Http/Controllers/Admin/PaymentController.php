<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexPaymentRequest;
use App\Models\Payment;
use App\Services\Admin\AdminPaymentDetailService;
use App\Services\Admin\AdminPaymentQuery;
use Illuminate\View\View;

final class PaymentController extends Controller
{
    public function index(IndexPaymentRequest $request, AdminPaymentQuery $payments): View
    {
        $filters = $request->validated();

        return view('admin.payments.index', [
            'payments' => $payments->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function show(Payment $payment, AdminPaymentDetailService $details): View
    {
        return view('admin.payments.show', $details->get(
            $payment,
            request()->user()?->can('activity_logs.view') === true,
        ));
    }
}
