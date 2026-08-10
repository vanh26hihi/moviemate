@extends('layouts.staff')

@section('title', 'Kiểm tra vé - MovieMate Staff')
@section('page-title', 'Kiểm tra vé')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="app-card border app-border rounded-3xl p-8 sm:p-12 text-center shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 right-0 w-[300px] h-[300px] bg-ai-start/10 rounded-full blur-[90px] -translate-y-1/2 translate-x-1/2"></div>

        <div class="relative z-10">
            <div class="w-20 h-20 rounded-3xl bg-ai-start/10 text-ai-start flex items-center justify-center mx-auto mb-5 border border-ai-start/20">
                <i class="ph-bold ph-qr-code text-5xl"></i>
            </div>

            <h1 class="text-3xl font-bold app-text mb-2">Kiểm tra vé khách hàng</h1>
            <p class="app-muted mb-8 max-w-xl mx-auto">
                Nhập mã booking_code trên vé điện tử hoặc mã QR để kiểm tra trạng thái vé trong hệ thống.
            </p>

            <form action="{{ route('staff.tickets.check.submit') }}" method="POST" class="max-w-lg mx-auto">
                @csrf
                <label for="booking_code" class="block text-left text-sm font-bold app-text mb-3">Mã vé booking_code</label>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input
                        id="booking_code"
                        type="text"
                        name="booking_code"
                        value="{{ old('booking_code') }}"
                        class="flex-grow px-4 py-3 app-input border app-border rounded-2xl app-text font-mono text-center sm:text-left focus:outline-none focus:border-ai-start uppercase tracking-widest"
                        placeholder="MMT-2026-0001"
                        autocomplete="off"
                        autofocus
                    >
                    <button type="submit" class="px-6 py-3 bg-ai-start text-white font-bold rounded-2xl hover:bg-ai-end transition-colors whitespace-nowrap">
                        Kiểm tra
                    </button>
                </div>
            </form>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('staff.tickets.index') }}" class="inline-flex items-center justify-center gap-2 px-5 py-3 app-secondary border app-border app-text rounded-2xl font-semibold hover:border-ai-start transition-colors">
                    <i class="ph ph-list-checks"></i>
                    Xem danh sách vé
                </a>
                <a href="{{ route('staff.dashboard') }}" class="inline-flex items-center justify-center gap-2 px-5 py-3 app-secondary border app-border app-text rounded-2xl font-semibold hover:border-brand-start transition-colors">
                    <i class="ph ph-squares-four"></i>
                    Về dashboard
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
