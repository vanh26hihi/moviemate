@extends('layouts.user')

@section('title', 'Đăng nhập nhân viên - MovieMate')

@section('content')
<section class="flex min-h-screen items-center justify-center px-6 py-16">
    <div class="cinema-card w-full max-w-md p-8">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-r from-brand-start to-brand-end text-3xl text-white"><i class="ph-fill ph-ticket"></i></div>
            <h1 class="text-3xl font-black app-text">Đăng nhập nhân viên</h1>
            <p class="mt-2 app-muted">Kiểm tra vé và hỗ trợ khách hàng tại rạp.</p>
        </div>

        <form action="{{ route('login.store') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="staff-email" class="cinema-label">Email nhân viên</label>
                <input id="staff-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="cinema-input" placeholder="Nhập email nhân viên">
                @error('email')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="staff-password" class="cinema-label">Mật khẩu</label>
                <input id="staff-password" name="password" type="password" autocomplete="current-password" required class="cinema-input" placeholder="Nhập mật khẩu">
                @error('password')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full justify-center">Đăng nhập</button>
        </form>
    </div>
</section>
@endsection
