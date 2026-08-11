@extends('layouts.staff')

@section('title', 'Soát vé - MovieMate')
@section('page-title', 'Soát vé')
@section('suppress-global-validation-summary', true)

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <header>
        <h1 class="admin-page-title">SOÁT VÉ</h1>
        <p class="admin-page-subtitle">Quét QR hoặc nhập mã vé, kiểm tra thông tin rồi xác nhận cho khách vào.</p>
    </header>

    @if(session('checkin_result'))
        @php($checkin = session('checkin_result'))
        <section class="rounded-2xl border p-5 {{ $checkin['result'] === 'accepted' ? 'border-success/40 bg-success/10' : 'border-error/40 bg-error/10' }}" role="alert">
            <h2 class="text-lg font-extrabold">{{ $checkin['result'] === 'accepted' ? 'Đã xác nhận cho vào' : 'Vé đã được sử dụng' }}</h2>
            <p class="mt-1">{{ $checkin['message'] }}</p>
            @if($checkin['ticket_code'])<p class="mt-2 font-mono text-sm">{{ $checkin['ticket_code'] }} · Ghế {{ $checkin['seat'] }} · {{ $checkin['used_at'] }}</p>@endif
        </section>
    @endif

    <form method="POST" action="{{ route('staff.tickets.consume') }}" class="cinema-card p-6" autocomplete="off">
        @csrf
        <label for="ticket" class="text-sm font-bold app-text">Quét QR / nhập mã vé</label>
        <input id="ticket" name="ticket" type="password" class="cinema-input mt-2" required maxlength="512" autocomplete="off" autofocus>
        @error('ticket')<p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        <button class="btn-primary mt-5" type="submit"><i class="ph ph-magnifying-glass"></i>Kiểm tra vé</button>
    </form>

    @if(session('ticket_lookup'))
        @php($ticket = session('ticket_lookup'))
        <section class="cinema-card p-6" aria-labelledby="door-result-title">
            <h2 id="door-result-title" class="text-xl font-extrabold app-heading">Kết quả xác minh</h2>
            <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                <div><dt class="app-muted">Phim</dt><dd class="font-bold">{{ $ticket['movie'] }}</dd></div>
                <div><dt class="app-muted">Suất</dt><dd class="font-bold">{{ $ticket['showtime'] }}</dd></div>
                <div><dt class="app-muted">Chi nhánh</dt><dd class="font-bold">{{ $ticket['cinema'] }}</dd></div>
                <div><dt class="app-muted">Phòng</dt><dd class="font-bold">{{ $ticket['room'] }}</dd></div>
                <div><dt class="app-muted">Ghế</dt><dd class="text-2xl font-extrabold text-brand-start">{{ $ticket['seat'] }}</dd></div>
                <div><dt class="app-muted">Trạng thái</dt><dd class="font-bold">{{ $ticket['status'] === 'used' ? 'Vé đã được sử dụng' : 'Chưa sử dụng' }}</dd></div>
            </dl>
            @if($ticket['status'] === 'unused')
                <form method="POST" action="{{ route('staff.admission-tickets.admit', $ticket['id']) }}" class="mt-6" data-submit-once>
                    @csrf
                    <button type="submit" class="btn-primary w-full">XÁC NHẬN CHO VÀO</button>
                </form>
            @else
                <p class="mt-5 rounded-xl bg-error/10 p-4 font-bold text-error">Vé đã được sử dụng lúc {{ $ticket['used_at'] }}.</p>
            @endif
        </section>
    @endif
</div>
@endsection
