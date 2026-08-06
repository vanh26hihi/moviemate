@extends('layouts.staff')

@section('title', 'Soát vé - MovieMate')
@section('page-title', 'Soát vé')
@section('suppress-global-validation-summary', true)

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Soát vé</h1>
        <p class="admin-page-subtitle">Quét mã QR trên vé để xác minh và ghi nhận lịch sử.</p>
    </div>
</div>

@if(session('checkin_result'))
    @php($checkin = session('checkin_result'))
    <section class="mb-6 rounded-2xl border p-5 {{ $checkin['result'] === 'accepted' ? 'border-success/40 bg-success/10' : ($checkin['result'] === 'already_used' ? 'border-warning/40 bg-warning/10' : 'border-error/40 bg-error/10') }}" role="alert">
        <h2 class="text-lg font-extrabold">{{ \App\Support\StatusLabel::for('ticket_checkin', $checkin['result']) }}</h2>
        <p class="mt-1">{{ $checkin['message'] }}</p>
        @if($checkin['booking_code'])<p class="mt-2 font-mono text-sm">{{ $checkin['booking_code'] }} · {{ $checkin['used_at'] }}</p>@endif
    </section>
@endif

<form method="POST" action="{{ route('staff.tickets.consume') }}" class="cinema-card mx-auto max-w-2xl p-6" autocomplete="off">
    @csrf
    <label for="ticket" class="text-sm font-bold app-text">Dữ liệu mã QR</label>
    <input id="ticket" name="ticket" type="password" class="cinema-input mt-2" required maxlength="512" autocomplete="off" autofocus aria-describedby="ticket-help">
    <p id="ticket-help" class="mt-2 text-sm app-muted">Mã được xử lý trên máy chủ và không được lưu trong lịch sử.</p>
    @error('ticket')<p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>@enderror
    <button class="btn-primary mt-5" type="submit"><i class="ph ph-qr-code"></i>Kiểm tra vé</button>
</form>
@endsection
