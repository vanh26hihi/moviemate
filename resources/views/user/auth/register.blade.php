@extends('layouts.user')

@section('title', 'Đăng ký - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-16 lg:px-10">

    <div class="mx-auto max-w-xl rounded-[36px] border border-white/10 bg-[#151A27] p-8 shadow-2xl lg:p-10">

        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-[#FF3D57] to-[#FF7A18] text-3xl">
                🎬
            </div>

            <h1 class="text-3xl font-black">Tạo tài khoản MovieMate</h1>
            <p class="mt-2 text-sm text-gray-400">
                Đăng ký để đặt vé và nhận gợi ý phim từ AI.
            </p>
        </div>

        <form class="space-y-5">
            <div>
                <label class="mb-2 block text-sm font-bold">Họ và tên</label>
                <input type="text" placeholder="Nguyễn Văn A" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 text-sm outline-none focus:border-[#FF7A18]">
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold">Email</label>
                <input type="email" placeholder="email@example.com" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 text-sm outline-none focus:border-[#FF7A18]">
            </div>

            <div>
                <label class="mb-2 block text-sm font-bold">Số điện thoại</label>
                <input type="text" placeholder="0987654321" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 text-sm outline-none focus:border-[#FF7A18]">
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-bold">Mật khẩu</label>
                    <input type="password" placeholder="********" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 text-sm outline-none focus:border-[#FF7A18]">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-bold">Nhập lại mật khẩu</label>
                    <input type="password" placeholder="********" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 text-sm outline-none focus:border-[#FF7A18]">
                </div>
            </div>

            <button type="button" class="w-full rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] py-4 font-bold shadow-xl shadow-red-500/30 transition hover:scale-105">
                Tạo tài khoản
            </button>

            <p class="text-center text-sm text-gray-400">
                Đã có tài khoản?
                <a href="/login" class="font-bold text-[#FF7A18]">Đăng nhập</a>
            </p>
        </form>

    </div>

</section>

@endsection