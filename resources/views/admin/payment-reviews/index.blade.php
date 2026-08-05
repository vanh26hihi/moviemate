@extends('layouts.admin')

@section('title', 'Kiểm tra thanh toán - MovieMate')
@section('page-title', 'Kiểm tra thanh toán')

@section('content')
    @if (session('payment_review_result'))
        <div class="mb-4 rounded-xl border border-green-500/30 bg-green-500/10 p-4 text-sm">
            {{ session('payment_review_result') }}
        </div>
    @endif

    @if (session('payment_review_error'))
        <div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm">
            {{ session('payment_review_error') }}
        </div>
    @endif

    <div class="app-card border app-border rounded-2xl overflow-hidden shadow-lg">
        <div class="p-5 border-b app-border">
            <h2 class="text-lg font-bold">Giao dịch cần nhân viên xác minh</h2>
            <p class="text-sm app-muted">Thao tác này truy vấn đơn hàng hiện có tại nhà cung cấp và không tạo giao dịch thay thế.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left whitespace-nowrap">
                <thead class="app-secondary text-xs uppercase app-muted">
                    <tr>
                        <th class="px-5 py-3">Thanh toán</th>
                        <th class="px-5 py-3">Đơn đặt vé</th>
                        <th class="px-5 py-3">Nhà cung cấp</th>
                        <th class="px-5 py-3 text-right">Số tiền</th>
                        <th class="px-5 py-3">Lý do</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)] text-sm">
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="px-5 py-4">#{{ $payment->id }}</td>
                            <td class="px-5 py-4">{{ $payment->booking->booking_code }}</td>
                            <td class="px-5 py-4">{{ $payment->provider }}</td>
                            <td class="px-5 py-4 text-right">{{ number_format((int) $payment->amount, 0, ',', '.') }} VNĐ</td>
                            <td class="px-5 py-4"><span>Cần nhân viên kiểm tra</span>@if($payment->failure_reason)<code class="mt-1 block text-xs app-muted">{{ $payment->failure_reason }}</code>@endif</td>
                            <td class="px-5 py-4 text-right">
                                <form method="POST" action="{{ route('admin.payment-reviews.resolve', ['paymentId' => $payment->id]) }}">
                                    @csrf
                                    <button class="rounded-xl bg-brand-start px-4 py-2 font-semibold text-white" type="submit">
                                        Kiểm tra giao dịch hiện có
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-6 text-center app-muted">Không có giao dịch nào đang chờ kiểm tra.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t app-border">{{ $payments->links() }}</div>
    </div>
@endsection
