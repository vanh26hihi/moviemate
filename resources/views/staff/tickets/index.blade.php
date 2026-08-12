@extends('layouts.staff')

@section('title', 'Tra cứu & in đơn - MovieMate')
@section('page-title', 'Tra cứu & in đơn')
@section('suppress-global-validation-summary', true)

@section('content')
<div class="space-y-6" data-ticket-scanner>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-title">Tra cứu & in đơn</h1>
            <p class="admin-page-subtitle">Quét QR đơn đặt vé hoặc nhập mã đơn để mở các tài liệu cần in.</p>
        </div>
        <span class="status-badge bg-brand-start/10 text-brand-start">{{ $cinema?->name ?? 'Tất cả chi nhánh được phép' }}</span>
    </header>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Quét bằng camera</h2>
            <p class="mt-2 text-sm app-muted">Camera cần HTTPS hoặc localhost và quyền truy cập từ trình duyệt.</p>
            <video data-scanner-video class="mt-4 aspect-video w-full rounded-2xl bg-black object-cover" muted playsinline></video>
            <p data-scanner-error class="mt-3 rounded-xl bg-error/10 p-3 text-sm text-error" role="alert" hidden></p>
            <div class="mt-4 flex gap-2">
                <button type="button" class="btn-primary" data-scanner-start><i class="ph ph-camera"></i>Mở camera</button>
                <button type="button" class="btn-secondary" data-scanner-stop hidden>Dừng camera</button>
            </div>
        </section>

        <form method="POST" action="{{ route('staff.tickets.resolve') }}" class="cinema-card p-6" autocomplete="off" data-submit-once>
            @csrf
            <h2 class="text-xl font-extrabold app-heading">Tra cứu thủ công</h2>
            <label for="ticket" class="cinema-label mt-5 block">Mã đơn hoặc QR đơn đặt vé</label>
            <textarea id="ticket" name="ticket" data-scanner-input class="cinema-input mt-2 min-h-32" required maxlength="512" autocomplete="off" spellcheck="false">{{ old('ticket') }}</textarea>
            <p class="mt-2 text-sm app-muted">Nhập chính xác mã MMT-… hoặc dữ liệu QR bảo mật. Máy chủ luôn xác minh đơn và phạm vi chi nhánh trước khi hiển thị.</p>
            @error('ticket')<p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>@enderror
            <button class="btn-primary mt-5" type="submit"><i class="ph ph-magnifying-glass"></i>Mở đơn</button>
        </form>
    </div>
</div>
@endsection
