@extends('layouts.staff')

@section('title', 'Không tìm thấy vé - MovieMate Staff')
@section('page-title', 'Kết quả kiểm tra vé')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-error/10 border-2 border-error rounded-3xl p-8 text-center shadow-lg shadow-error/20 mb-6">
        <div class="w-20 h-20 bg-error text-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-error/40">
            <i class="ph-bold ph-x text-4xl"></i>
        </div>

        <h1 class="text-3xl font-bold text-error mb-2">Không tìm thấy vé</h1>
        <p class="app-muted font-medium mb-8">Mã vé không tồn tại, đã bị hủy, hết hạn hoặc không đủ điều kiện sử dụng.</p>

        <div class="app-card border app-border rounded-2xl p-6 text-left mb-8">
            <p class="text-xs app-muted uppercase tracking-wider mb-2 text-center font-bold">Mã đã kiểm tra</p>
            <div class="app-secondary border app-border rounded-xl p-4 text-center">
                <p class="text-xl font-bold app-text font-mono tracking-widest opacity-70 line-through">
                    {{ $bookingCode ?? 'Không có mã' }}
                </p>
            </div>

            <div class="mt-6 space-y-2 text-sm app-muted">
                <p class="flex items-center gap-2"><i class="ph-fill ph-info text-brand-start"></i> Vui lòng kiểm tra lại:</p>
                <ul class="list-disc list-inside pl-4 space-y-1">
                    <li>Mã vé có bị sai ký tự không?</li>
                    <li>Vé đã bị hủy hoặc hết hạn?</li>
                    <li>Vé thuộc hệ thống hoặc cụm rạp khác?</li>
                </ul>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <a href="{{ route('staff.tickets.check') }}" class="w-full py-4 bg-error text-white rounded-2xl font-bold text-lg hover:bg-error/90 hover:shadow-lg hover:shadow-error/30 transition-all inline-block">
                Thử kiểm tra lại
            </a>
            <a href="{{ route('staff.dashboard') }}" class="w-full py-3 app-secondary border app-border app-text rounded-2xl font-semibold hover:border-error transition-colors inline-block">
                Về dashboard
            </a>
        </div>
    </div>
</div>
@endsection
