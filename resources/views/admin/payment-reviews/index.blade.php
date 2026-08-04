@extends('layouts.admin')

@section('title', 'Payment review - MovieMate Admin')
@section('page-title', 'Payment review')

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
            <h2 class="text-lg font-bold">Payments requiring an operator decision</h2>
            <p class="text-sm app-muted">This action queries the existing provider order. It never creates a replacement order.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left whitespace-nowrap">
                <thead class="app-secondary text-xs uppercase app-muted">
                    <tr>
                        <th class="px-5 py-3">Payment</th>
                        <th class="px-5 py-3">Booking</th>
                        <th class="px-5 py-3">Provider</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3">Reason</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)] text-sm">
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="px-5 py-4">#{{ $payment->id }}</td>
                            <td class="px-5 py-4">{{ $payment->booking->booking_code }}</td>
                            <td class="px-5 py-4">{{ $payment->provider }}</td>
                            <td class="px-5 py-4 text-right">{{ number_format($payment->amount) }} VND</td>
                            <td class="px-5 py-4">{{ $payment->failure_reason ?? 'manual_review' }}</td>
                            <td class="px-5 py-4 text-right">
                                <form method="POST" action="{{ route('admin.payment-reviews.resolve', ['paymentId' => $payment->id]) }}">
                                    @csrf
                                    <button class="rounded-xl bg-brand-start px-4 py-2 font-semibold text-white" type="submit">
                                        Query existing order
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-6 text-center app-muted">No payments are in review.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t app-border">{{ $payments->links() }}</div>
    </div>
@endsection
