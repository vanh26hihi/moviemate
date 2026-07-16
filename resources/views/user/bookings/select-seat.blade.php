@extends('layouts.user')

@section('title', 'Chọn ghế - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-12 lg:px-10">

    <div class="mx-auto max-w-[1440px]">

        <div class="mb-10 rounded-[24px] border border-white/10 bg-[#151A27] p-5">
            <div class="flex flex-wrap items-center justify-center gap-4 text-sm font-bold text-gray-400">
                <span class="text-[#FF7A18]">1. Chọn phim</span>
                <span>→</span>
                <span class="text-[#FF7A18]">2. Chọn suất</span>
                <span>→</span>
                <span class="text-white">3. Chọn ghế</span>
                <span>→</span>
                <span>4. Thanh toán</span>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-[1fr_380px]">

            <div class="rounded-[32px] border border-white/10 bg-[#151A27] p-8">

                <div class="mb-8">
                    <h1 class="text-4xl font-black">Chọn ghế</h1>
                    <p class="mt-3 text-gray-400">
                        Chọn vị trí ghế yêu thích của bạn trong phòng chiếu.
                    </p>
                </div>

                <div class="mb-10 grid gap-4 rounded-[24px] border border-white/10 bg-[#080A12] p-5 md:grid-cols-4">
                    <div>
                        <p class="text-sm text-gray-400">Phim</p>
                        <h3 class="mt-1 font-bold">Thanh Gươm Diệt Quỷ</h3>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Rạp</p>
                        <h3 class="mt-1 font-bold">MovieMate Hà Nội</h3>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Ngày chiếu</p>
                        <h3 class="mt-1 font-bold">20/05/2026</h3>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Giờ chiếu</p>
                        <h3 class="mt-1 font-bold">20:45</h3>
                    </div>
                </div>

                <div class="mb-12 text-center">
                    <div class="mx-auto h-3 max-w-2xl rounded-full bg-gradient-to-r from-transparent via-white to-transparent"></div>
                    <p class="mt-4 text-sm font-bold uppercase tracking-[0.4em] text-gray-400">
                        Màn hình
                    </p>
                </div>

                <div class="mx-auto max-w-4xl space-y-4">

                    @foreach (['A','B','C','D','E','F','G','H'] as $row)
                        <div class="flex items-center justify-center gap-3">
                            <span class="w-6 text-center text-sm font-bold text-gray-400">{{ $row }}</span>

                            @for ($i = 1; $i <= 12; $i++)
                                @php
                                    $seat = $row . $i;
                                    $isBooked = in_array($seat, ['A3','A4','C7','D8','F5']);
                                    $isSelected = in_array($seat, ['E5','E6']);
                                    $isVip = in_array($row, ['E','F','G']);
                                @endphp

                                <button
                                    class="
                                        flex h-10 w-10 items-center justify-center rounded-xl text-xs font-bold transition
                                        {{ $isBooked ? 'cursor-not-allowed bg-gray-700 text-gray-500' : '' }}
                                        {{ $isSelected ? 'bg-[#FF3D57] text-white shadow-lg shadow-red-500/30' : '' }}
                                        {{ !$isBooked && !$isSelected && $isVip ? 'bg-purple-600 text-white hover:bg-[#FF3D57]' : '' }}
                                        {{ !$isBooked && !$isSelected && !$isVip ? 'bg-[#374151] text-white hover:bg-[#FF3D57]' : '' }}
                                    "
                                    {{ $isBooked ? 'disabled' : '' }}
                                >
                                    {{ $i }}
                                </button>
                            @endfor
                        </div>
                    @endforeach

                </div>

                <div class="mt-12 flex flex-wrap justify-center gap-6 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="h-5 w-5 rounded-md bg-[#374151]"></span>
                        <span class="text-gray-400">Ghế trống</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="h-5 w-5 rounded-md bg-[#FF3D57]"></span>
                        <span class="text-gray-400">Đang chọn</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="h-5 w-5 rounded-md bg-purple-600"></span>
                        <span class="text-gray-400">Ghế VIP</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="h-5 w-5 rounded-md bg-gray-700"></span>
                        <span class="text-gray-400">Đã đặt</span>
                    </div>
                </div>

            </div>

            <div class="h-fit rounded-[32px] border border-white/10 bg-[#151A27] p-6 lg:sticky lg:top-28">

                <h2 class="mb-6 text-2xl font-black">Thông tin đặt vé</h2>

                <div class="mb-6 flex gap-4">
                    <img
                        src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=400&auto=format&fit=crop"
                        class="h-28 w-20 rounded-2xl object-cover"
                        alt="Poster"
                    >

                    <div>
                        <h3 class="font-black">Thanh Gươm Diệt Quỷ</h3>
                        <p class="mt-2 text-sm text-gray-400">Hành động, Hoạt hình</p>
                        <p class="mt-2 text-sm text-yellow-300">⭐ 4.8</p>
                    </div>
                </div>

                <div class="space-y-4 border-y border-white/10 py-5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Rạp</span>
                        <span class="font-bold">MovieMate Hà Nội</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-400">Phòng</span>
                        <span class="font-bold">Room 01</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-400">Suất chiếu</span>
                        <span class="font-bold">20:45 - 20/05/2026</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-400">Ghế</span>
                        <span class="font-bold text-[#FF7A18]">E5, E6</span>
                    </div>
                </div>

                <div class="space-y-4 py-5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Giá vé</span>
                        <span class="font-bold">90.000đ x 2</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-400">Phụ phí</span>
                        <span class="font-bold">0đ</span>
                    </div>

                    <div class="flex justify-between text-xl">
                        <span class="font-black">Tổng tiền</span>
                        <span class="font-black text-[#FF7A18]">180.000đ</span>
                    </div>
                </div>

                <a href="/booking/checkout" class="block rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] py-4 text-center font-bold shadow-xl shadow-red-500/30 transition hover:scale-105">
                    Tiếp tục thanh toán
                </a>

            </div>

        </div>

    </div>

</section>

@endsection