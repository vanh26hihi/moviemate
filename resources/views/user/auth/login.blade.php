@extends('layouts.user')

@section('title', 'Đăng nhập - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-16 lg:px-10">

    <div class="mx-auto grid max-w-[1200px] overflow-hidden rounded-[36px] border border-white/10 bg-[#151A27] shadow-2xl lg:grid-cols-2">

        <div class="relative hidden min-h-[650px] lg:block">
            <img
                src="https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=1000&auto=format&fit=crop"
                class="h-full w-full object-cover"
                alt="Cinema"
            >
            <div class="absolute inset-0 bg-gradient-to-t from-[#080A12] via-[#080A12]/70 to-transparent"></div>

            <div class="absolute bottom-10 left-10 right-10">
                <div class="mb-5 inline-flex rounded-full bg-[#7C3AED]/20 px-4 py-2 text-sm font-bold text-purple-200">
                    ✨ AI Cinema Booking
                </div>

                <h1 class="text-4xl font-black leading-tight">
                    Chào mừng trở lại MovieMate
                </h1>

                <p class="mt-4 text-gray-300">
                    Đăng nhập để tiếp tục đặt vé, xem lịch sử và nhận gợi ý phim phù hợp từ AI.
                </p>
            </div>
        </div>

        <div class="flex items-center justify-center p-8 lg:p-12">

            <div class="w-full max-w-md">

                <div class="mb-8 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-[#FF3D57] to-[#FF7A18] text-3xl shadow-lg shadow-red-500/30">
                        🎬
                    </div>

                    <h2 class="text-3xl font-black">Đăng nhập</h2>
                    <p class="mt-2 text-sm text-gray-400">
                        Nhập thông tin tài khoản của bạn
                    </p>
                </div>

                <form class="space-y-5">

                    <div>
                        <label class="mb-2 block text-sm font-bold">Email</label>
                        <input
                            type="email"
                            placeholder="Nhập email"
                            class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 text-sm outline-none placeholder:text-gray-500 focus:border-[#FF7A18]"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-bold">Mật khẩu</label>
                        <input
                            type="password"
                            placeholder="Nhập mật khẩu"
                            class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 text-sm outline-none placeholder:text-gray-500 focus:border-[#FF7A18]"
                        >
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 text-gray-400">
                            <input type="checkbox" class="rounded">
                            Ghi nhớ đăng nhập
                        </label>

                        <a href="#" class="font-bold text-[#FF7A18]">Quên mật khẩu?</a>
                    </div>

                    <button type="button" class="w-full rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] py-4 font-bold shadow-xl shadow-red-500/30 transition hover:scale-105">
                        Đăng nhập
                    </button>

                    <p class="text-center text-sm text-gray-400">
                        Chưa có tài khoản?
                        <a href="/register" class="font-bold text-[#FF7A18]">Đăng ký ngay</a>
                    </p>

                </form>

            </div>

        </div>

    </div>

</section>

@endsection