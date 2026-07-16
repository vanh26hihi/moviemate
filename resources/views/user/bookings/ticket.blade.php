@extends('layouts.user')

@section('title', 'Vé của tôi - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-12">

    <div class="mx-auto max-w-3xl">

        <div class="mb-10 text-center">
            <p class="mb-2 text-sm font-bold uppercase tracking-[0.3em] text-[#FF7A18]">My Ticket</p>
            <h1 class="text-4xl font-black">Vé điện tử</h1>
            <p class="mt-3 text-gray-400">Đưa mã QR này cho nhân viên để kiểm tra vé.</p>
        </div>

        <div class="overflow-hidden rounded-[36px] border border-white/10 bg-[#151A27] shadow-2xl">

            <div class="bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-black">MovieMate Ticket</h2>
                    <span class="rounded-full bg-white/20 px-4 py-2 text-sm font-bold">
                        Chưa sử dụng
                    </span>
                </div>
            </div>

            <div class="grid gap-8 p-8 md:grid-cols-[1fr_220px]">

                <div class="space-y-5">
                    <div>
                        <p class="text-sm text-gray-400">Phim</p>
                        <h3 class="text-2xl font-black">Thanh Gươm Diệt Quỷ</h3>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-sm text-gray-400">Rạp</p>
                            <p class="font-bold">MovieMate Hà Nội</p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-400">Phòng</p>
                            <p class="font-bold">Room 01</p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-400">Ngày giờ</p>
                            <p class="font-bold">20:45 - 20/05/2026</p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-400">Ghế</p>
                            <p class="font-bold text-[#FF7A18]">E5, E6</p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-400">Mã vé</p>
                            <p class="font-bold">MMT-2026-0001</p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-400">Tổng tiền</p>
                            <p class="font-bold">180.000đ</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col items-center justify-center">
                    <div class="flex h-52 w-52 items-center justify-center rounded-3xl bg-white text-5xl font-black text-black">
                        QR
                    </div>
                    <p class="mt-4 text-center text-sm text-gray-400">Mã QR soát vé</p>
                </div>

            </div>

        </div>

    </div>

</section>

@endsection