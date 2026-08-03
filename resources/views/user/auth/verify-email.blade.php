@extends('layouts.user')

@section('title', 'Xác thực email - MovieMate')

@section('content')
<section class="relative min-h-[calc(100svh-5rem)] flex items-center justify-center overflow-hidden px-4 py-14">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_22%_20%,rgba(255,61,87,0.18),transparent_35%),radial-gradient(circle_at_78%_76%,rgba(108,43,217,0.18),transparent_40%)]"></div>
    <div class="relative w-full max-w-lg cinema-card rounded-3xl p-8 sm:p-10 text-center">
        <div class="mx-auto w-20 h-20 rounded-3xl bg-ai-start/10 text-ai-start flex items-center justify-center"><i class="ph-fill ph-envelope-open text-4xl"></i></div>
        <h1 class="mt-6 text-3xl font-extrabold app-text">Xác thực email</h1>
        <p class="mx-auto mt-3 max-w-md app-muted">Vui lòng kiểm tra hộp thư và làm theo hướng dẫn xác thực tài khoản MovieMate.</p>

        @if (session('status') === 'verification-link-sent')
            <div class="mt-6 rounded-2xl border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">Liên kết xác thực mới đã được gửi.</div>
        @endif

        <button type="button" disabled class="mt-7 w-full cursor-not-allowed rounded-xl border app-border app-card px-7 py-3 font-bold app-muted opacity-60 sm:w-auto">Gửi lại email xác thực</button>
        <p class="mt-5 text-xs app-muted">Tính năng gửi lại email xác thực đang được hoàn thiện.</p>
    </div>
</section>
@endsection
