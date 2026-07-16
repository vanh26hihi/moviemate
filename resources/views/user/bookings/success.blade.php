@extends('layouts.user')

@section('title', 'Đặt vé thành công - MovieMate')

@section('content')

<section class="flex min-h-screen items-center justify-center bg-[#080A12] px-6 py-16">

    <div class="max-w-2xl rounded-[36px] border border-white/10 bg-[#151A27] p-8 text-center shadow-2xl shadow-green-500/10">

        <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-green-500/20 text-5xl text-green-400">
            ✓
        </div>

        <h1 class="text-4xl font-black">Đặt vé thành công!</h1>
        <p class="mt-4 text-gray-400">
            Vé của bạn đã được tạo. Vui lòng xuất trình mã QR khi đến rạp.
        </p>

        <div class="mx-auto my-8 flex h-48 w-48 items-center justify-center rounded-3xl bg-white text-6xl font-black text-black">
            QR
        </div>

        <div class="rounded-3xl border border-white/10 bg-[#080A12] p-6 text-left">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-gray-400">Mã vé</p>
                    <p class="font-black text-[#FF7A18]">MMT-2026-0001</p>
                </div>

                <div>
                    <p class="text-sm text-gray-400">Phim</p>
                    <p class="font-bold">Thanh Gươm Diệt Quỷ</p>
                </div>

                <div>
                    <p class="text-sm text-gray-400">Suất chiếu</p>
                    <p class="font-bold">20:45 - 20/05/2026</p>
                </div>

                <div>
                    <p class="text-sm text-gray-400">Ghế</p>
                    <p class="font-bold">E5, E6</p>
                </div>
            </div>
        </div>

        <div class="mt-8 flex flex-wrap justify-center gap-4">
            <a href="/my-ticket" class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-8 py-4 font-bold">
                Xem vé của tôi
            </a>

            <a href="/" class="rounded-2xl border border-white/10 px-8 py-4 font-bold transition hover:border-[#FF7A18] hover:text-[#FF7A18]">
                Về trang chủ
            </a>
        </div>

    </div>

</section>

@endsection