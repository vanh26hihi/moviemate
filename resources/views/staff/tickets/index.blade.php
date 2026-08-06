@extends('layouts.staff')

@section('title', 'Tra cứu vé - MovieMate')
@section('page-title', 'Tra cứu và quét vé')
@section('suppress-global-validation-summary', true)

@section('content')
<div class="space-y-6" data-ticket-scanner>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-title">Tra cứu vé</h1>
            <p class="admin-page-subtitle">Quét QR để xem trước dữ liệu xác thực. Bước này không in và không soát vé.</p>
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

        <form method="POST" action="{{ route('staff.tickets.resolve') }}" class="cinema-card p-6" autocomplete="off">
            @csrf
            <h2 class="text-xl font-extrabold app-heading">Nhập mã thủ công</h2>
            <label for="ticket" class="cinema-label mt-5 block">Dữ liệu mã QR</label>
            <textarea id="ticket" name="ticket" data-scanner-input class="cinema-input mt-2 min-h-32" required maxlength="512" autocomplete="off" spellcheck="false">{{ old('ticket') }}</textarea>
            <p class="mt-2 text-sm app-muted">Dữ liệu chỉ được gửi bằng POST để xác minh trên máy chủ; không ghi vào nhật ký hoạt động.</p>
            @error('ticket')<p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>@enderror
            <button class="btn-primary mt-5" type="submit"><i class="ph ph-magnifying-glass"></i>Xem trước vé</button>
        </form>
    </div>
</div>
@endsection
