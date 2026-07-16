@extends('layouts.user')

@section('title', 'Chi tiết phim - MovieMate')

@section('content')

<section class="relative min-h-screen overflow-hidden bg-[#080A12]">

    <div class="absolute inset-x-0 top-0 h-[650px]">
        <img
            src="https://images.unsplash.com/photo-1440404653325-ab127d49abc1?q=80&w=1600&auto=format&fit=crop"
            class="h-full w-full object-cover opacity-40"
            alt="Cover"
        >
        <div class="absolute inset-0 bg-gradient-to-r from-[#080A12] via-[#080A12]/90 to-[#080A12]/50"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[#080A12] via-transparent to-transparent"></div>
    </div>

    <div class="relative mx-auto max-w-[1440px] px-6 py-16 lg:px-10">

        <div class="grid gap-10 lg:grid-cols-[360px_1fr]">

            <div>
                <div class="overflow-hidden rounded-[30px] border border-white/10 bg-white/10 p-3 shadow-2xl shadow-red-500/20 backdrop-blur">
                    <img
                        src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=700&auto=format&fit=crop"
                        class="h-[540px] w-full rounded-[24px] object-cover"
                        alt="Poster"
                    >
                </div>
            </div>

            <div class="pt-10">
                <div class="mb-5 flex flex-wrap gap-3">
                    <span class="rounded-full bg-green-500 px-4 py-2 text-sm font-bold">
                        Đang chiếu
                    </span>
                    <span class="rounded-full bg-white/10 px-4 py-2 text-sm font-bold">
                        T16
                    </span>
                    <span class="rounded-full bg-yellow-500/20 px-4 py-2 text-sm font-bold text-yellow-300">
                        ⭐ 4.8
                    </span>
                </div>

                <h1 class="text-5xl font-black leading-tight md:text-7xl">
                    Thanh Gươm Diệt Quỷ
                </h1>

                <p class="mt-5 max-w-3xl text-lg leading-8 text-gray-300">
                    Một cuộc phiêu lưu đầy kịch tính, nơi các nhân vật phải chiến đấu để bảo vệ những điều quan trọng nhất.
                    Bộ phim mang đến trải nghiệm điện ảnh hấp dẫn với hình ảnh mãn nhãn và cảm xúc mạnh mẽ.
                </p>

                <div class="mt-8 grid max-w-3xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm text-gray-400">Thể loại</p>
                        <h3 class="mt-1 font-bold">Hành động</h3>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm text-gray-400">Thời lượng</p>
                        <h3 class="mt-1 font-bold">115 phút</h3>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm text-gray-400">Quốc gia</p>
                        <h3 class="mt-1 font-bold">Nhật Bản</h3>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm text-gray-400">Khởi chiếu</p>
                        <h3 class="mt-1 font-bold">20/05/2026</h3>
                    </div>
                </div>

                <div class="mt-9 flex flex-wrap gap-4">
                    <a href="#showtimes" class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-8 py-4 font-bold shadow-xl shadow-red-500/30 transition hover:scale-105">
                        Đặt vé ngay
                    </a>

                    <button class="rounded-2xl border border-white/10 bg-white/5 px-8 py-4 font-bold transition hover:border-[#FF7A18] hover:text-[#FF7A18]">
                        Xem trailer
                    </button>

                    <a href="/ai/recommend" class="rounded-2xl bg-gradient-to-r from-[#7C3AED] to-[#2563EB] px-8 py-4 font-bold shadow-xl shadow-purple-500/30 transition hover:scale-105">
                        Hỏi AI về phim này
                    </a>
                </div>
            </div>

        </div>

        <div id="showtimes" class="mt-24 rounded-[32px] border border-white/10 bg-[#151A27] p-8">
            <div class="mb-8">
                <p class="mb-2 text-sm font-bold uppercase tracking-[0.3em] text-[#FF7A18]">Showtimes</p>
                <h2 class="text-4xl font-black">Lịch chiếu</h2>
            </div>

            <div class="mb-8 flex gap-4 overflow-x-auto pb-2">
                @foreach (['Hôm nay', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'] as $day)
                    <button class="min-w-[130px] rounded-2xl border border-white/10 bg-[#080A12] p-4 text-left transition hover:border-[#FF7A18] hover:bg-[#FF7A18]/10">
                        <p class="font-bold">{{ $day }}</p>
                        <p class="mt-1 text-sm text-gray-400">20/05</p>
                    </button>
                @endforeach
            </div>

            @foreach (range(1, 3) as $c)
                <div class="mb-6 rounded-[24px] border border-white/10 bg-[#080A12] p-6">
                    <div class="mb-5 flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h3 class="text-2xl font-black">MovieMate Cinema {{ $c }}</h3>
                            <p class="mt-2 text-sm text-gray-400">Tầng 5, Vincom Bà Triệu, Hà Nội</p>
                        </div>

                        <span class="rounded-full bg-green-500/20 px-4 py-2 text-sm font-bold text-green-400">
                            Còn vé
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @foreach (['09:30', '11:45', '14:00', '18:30', '20:45', '22:30'] as $time)
                            <a href="/booking/select-seat"
                               class="rounded-xl border border-white/10 px-5 py-3 font-bold transition hover:border-[#FF7A18] hover:bg-gradient-to-r hover:from-[#FF3D57] hover:to-[#FF7A18]">
                                {{ $time }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

        </div>

    </div>
</section>

@endsection