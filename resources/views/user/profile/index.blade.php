@extends('layouts.user')

@section('title', 'Trang cá nhân - MovieMate')

@section('content')
<section class="cinema-surface min-h-screen py-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @auth
            @php($profileUser = auth()->user())
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
                <aside class="lg:col-span-1">
                    <div class="cinema-card sticky top-24 flex flex-col items-center rounded-3xl p-6 text-center">
                        <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-brand-start to-brand-end text-3xl font-black text-white shadow-lg shadow-brand-start/25">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($profileUser->name, 0, 1)) }}
                        </div>
                        <h1 class="text-xl font-extrabold app-text">{{ $profileUser->name }}</h1>
                        <p class="mt-1 break-all text-sm app-muted">{{ $profileUser->email }}</p>
                        <div class="mt-5 w-full rounded-2xl border border-ai-start/25 bg-ai-start/10 px-4 py-3 text-left">
                            <p class="text-xs app-muted">Tài khoản MovieMate</p>
                            <p class="mt-1 font-extrabold text-ai-start">{{ $profileUser->email_verified_at ? 'Đã xác thực email' : 'Chưa xác thực email' }}</p>
                            <p class="mt-2 text-xs app-muted">Tham gia {{ $profileUser->created_at?->format('d/m/Y') ?? '—' }}</p>
                        </div>
                        <div class="mt-5 w-full space-y-1 text-left">
                            <a href="{{ route('user.profile') }}" class="flex items-center gap-3 rounded-xl border border-brand-start/20 bg-brand-start/10 px-4 py-2.5 text-sm font-bold text-brand-start"><i class="ph-fill ph-user text-lg"></i> Thông tin cá nhân</a>
                            <a href="{{ route('user.bookings.history') }}" class="flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium app-muted transition-colors hover:bg-brand-start/5 hover:text-brand-start"><i class="ph ph-ticket text-lg"></i> Lịch sử đặt vé</a>
                        </div>
                    </div>
                </aside>

                <div class="lg:col-span-3">
                    <div class="cinema-card rounded-3xl p-6 sm:p-8">
                        <div class="mb-7 border-b pb-5 app-border"><h2 class="text-2xl font-extrabold app-text">Thông tin cá nhân</h2><p class="mt-2 text-sm app-muted">Thông tin tài khoản MovieMate của bạn.</p></div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border app-border app-secondary p-4"><p class="text-xs app-muted">Họ và tên</p><p class="mt-1 font-bold app-text">{{ $profileUser->name }}</p></div>
                            <div class="rounded-2xl border app-border app-secondary p-4"><p class="text-xs app-muted">Email</p><p class="mt-1 break-all font-bold app-text">{{ $profileUser->email }}</p></div>
                            <div class="rounded-2xl border app-border app-secondary p-4"><p class="text-xs app-muted">Ngày tham gia</p><p class="mt-1 font-bold app-text">{{ $profileUser->created_at?->format('d/m/Y') ?? '—' }}</p></div>
                            <div class="rounded-2xl border app-border app-secondary p-4"><p class="text-xs app-muted">Xác thực email</p><p class="mt-1 font-bold text-ai-start">{{ $profileUser->email_verified_at ? 'Đã xác thực' : 'Chưa xác thực' }}</p></div>
                        </div>
                        <div class="mt-6 rounded-2xl border border-brand-start/20 bg-brand-start/5 p-4 text-sm app-muted"><i class="ph-fill ph-info mr-2 text-brand-start"></i>Chức năng cập nhật hồ sơ sẽ hiển thị khi TEAM cung cấp route xử lý tương ứng.</div>
                    </div>
                </div>
            </div>
        @else
            <div class="cinema-card mx-auto max-w-xl rounded-3xl p-8 text-center sm:p-10">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"><i class="ph-fill ph-user-circle text-4xl"></i></div>
                <h1 class="mt-5 text-2xl font-extrabold app-text">Đăng nhập để xem hồ sơ</h1>
                <p class="mx-auto mt-2 max-w-md app-muted">Thông tin thành viên và lịch sử đặt vé sẽ hiển thị sau khi bạn đăng nhập.</p>
                <div class="mt-6 flex flex-wrap justify-center gap-3"><a href="{{ route('login') }}" class="btn-primary">Đăng nhập</a><a href="{{ route('register') }}" class="btn-secondary">Đăng ký</a></div>
            </div>
        @endauth
    </div>
</section>
@endsection
