@extends('layouts.user')

@section('title', 'Quên mật khẩu - MovieMate')

@section('content')
@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    $resetEnabled = \Illuminate\Support\Facades\Route::has('password.email');
@endphp
<section class="relative min-h-[calc(100svh-5rem)] flex items-center justify-center overflow-hidden px-4 py-14">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,rgba(255,61,87,0.18),transparent_35%),radial-gradient(circle_at_80%_80%,rgba(108,43,217,0.18),transparent_40%)]"></div>
    <div class="relative w-full max-w-md cinema-card rounded-3xl p-7 sm:p-9">
        <div class="w-14 h-14 rounded-2xl bg-brand-start/10 text-brand-start flex items-center justify-center mb-6"><i class="ph-fill ph-lock-key-open text-3xl"></i></div>
        <h1 class="text-3xl font-extrabold app-text">Quên mật khẩu?</h1>
        <p class="mt-2 mb-7 app-muted">Nhập email tài khoản để nhận hướng dẫn đặt lại mật khẩu.</p>

        @if (session('status'))
            <div class="mb-5 rounded-2xl border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-5 rounded-2xl border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error">{{ $errors->first() }}</div>
        @endif

        <form action="{{ $resetEnabled ? route('password.email') : '#' }}" method="POST" class="space-y-5" @unless($resetEnabled) onsubmit="return false" @endunless>
            @csrf
            <div>
                <label for="email" class="block text-sm font-semibold app-text-soft mb-2">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" class="app-input w-full px-4 py-3 border app-border rounded-xl focus:outline-none focus:border-brand-start" placeholder="ban@example.com" @disabled(!$resetEnabled)>
                @error('email')<p class="mt-2 text-xs font-semibold text-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" @disabled(!$resetEnabled) class="w-full py-3 rounded-xl bg-gradient-to-r from-brand-start to-brand-end text-white font-bold disabled:cursor-not-allowed disabled:opacity-50">Gửi hướng dẫn</button>
        </form>

        @unless($resetEnabled)
            <p class="mt-4 rounded-xl border app-border app-secondary px-4 py-3 text-xs app-muted">Tính năng sẽ khả dụng khi backend TEAM cung cấp route đặt lại mật khẩu.</p>
        @endunless
        @if (Route::has('login'))
            <a href="{{ route('login') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-brand-start"><i class="ph-bold ph-arrow-left"></i>Quay lại đăng nhập</a>
        @endif
    </div>
</section>
@endsection
