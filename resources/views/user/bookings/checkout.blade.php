@extends('layouts.user')

@section('title', 'Thanh toán - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-12 lg:px-10">

    <div class="mx-auto max-w-[1200px]">

        <div class="mb-10">
            <p class="mb-2 text-sm font-bold uppercase tracking-[0.3em] text-[#FF7A18]">Checkout</p>
            <h1 class="text-4xl font-black">Xác nhận đặt vé</h1>
            <p class="mt-3 text-gray-400">Kiểm tra thông tin vé trước khi hoàn tất đặt vé.</p>
        </div>

        <div class="grid gap-8 lg:grid-cols-[1fr_420px]">

            <div class="space-y-6">
                <div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
                    <h2 class="mb-5 text-2xl font-black">Thông tin người đặt</h2>

                    <div class="grid gap-4 md:grid-cols-2">
                        <input placeholder="Họ và tên" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
                        <input placeholder="Số điện thoại" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
                        <input placeholder="Email" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18] md:col-span-2">
                    </div>
                </div>

                <div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
                    <h2 class="mb-5 text-2xl font-black">Phương thức thanh toán</h2>

                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="cursor-pointer rounded-2xl border border-[#FF7A18] bg-[#FF7A18]/10 p-5">
                            <input type="radio" checked>
                            <p class="mt-3 font-bold">Thanh toán giả lập</p>
                            <p class="mt-1 text-sm text-gray-400">Dùng cho demo</p>
                        </label>

                        <label class="cursor-pointer rounded-2xl border border-white/10 bg-[#080A12] p-5">
                            <input type="radio">
                            <p class="mt-3 font-bold">Thanh toán tại quầy</p>
                            <p class="mt-1 text-sm text-gray-400">Giữ vé tạm thời</p>
                        </label>

                        <label class="cursor-pointer rounded-2xl border border-white/10 bg-[#080A12] p-5">
                            <input type="radio">
                            <p class="mt-3 font-bold">VNPay Sandbox</p>
                            <p class="mt-1 text-sm text-gray-400">Nâng cấp sau</p>
                        </label>
                    </div>
                </div>
            </div>

            <div class="h-fit rounded-[28px] border border-white/10 bg-[#151A27] p-6">
                <h2 class="mb-6 text-2xl font-black">Tóm tắt vé</h2>

                <div class="mb-6 flex gap-4">
                    <img
                        src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=400&auto=format&fit=crop"
                        class="h-32 w-24 rounded-2xl object-cover"
                        alt="Poster"
                    >

                    <div>
                        <h3 class="font-black">Thanh Gươm Diệt Quỷ</h3>
                        <p class="mt-2 text-sm text-gray-400">MovieMate Hà Nội</p>
                        <p class="mt-2 text-sm text-gray-400">20:45 - 20/05/2026</p>
                    </div>
                </div>

                <div class="space-y-4 border-y border-white/10 py-5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Ghế</span>
                        <span class="font-bold">E5, E6</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-400">Số lượng</span>
                        <span class="font-bold">2 vé</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-400">Tạm tính</span>
                        <span class="font-bold">180.000đ</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-400">Giảm giá</span>
                        <span class="font-bold">0đ</span>
                    </div>
                </div>

                <div class="flex justify-between py-5 text-xl">
                    <span class="font-black">Tổng cộng</span>
                    <span class="font-black text-[#FF7A18]">180.000đ</span>
                </div>

                <a href="/booking/success" class="block rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] py-4 text-center font-bold shadow-xl shadow-red-500/30 transition hover:scale-105">
                    Xác nhận đặt vé
                </a>
            </div>

        </div>
    </div>

</section>

@endsection