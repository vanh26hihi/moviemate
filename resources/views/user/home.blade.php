@extends('layouts.user')

@section('title', 'MovieMate - Đặt vé xem phim thông minh cùng AI')

@section('content')

<section class="relative min-h-[760px] overflow-hidden bg-[#080A12]">

    <div class="absolute inset-0">
        <img
            src="https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=1600&auto=format&fit=crop"
            class="h-full w-full object-cover opacity-40"
            alt="Cinema"
        >
        <div class="absolute inset-0 bg-gradient-to-r from-[#080A12] via-[#080A12]/90 to-[#080A12]/40"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[#080A12] via-transparent to-transparent"></div>
    </div>

    <div class="absolute left-20 top-32 h-72 w-72 rounded-full bg-[#FF3D57]/20 blur-[120px]"></div>
    <div class="absolute right-20 top-40 h-72 w-72 rounded-full bg-[#7C3AED]/25 blur-[120px]"></div>

    <div class="relative mx-auto grid min-h-[760px] max-w-[1440px] items-center gap-10 px-6 lg:grid-cols-2 lg:px-10">

        <div>
            <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#7C3AED]/40 bg-[#7C3AED]/10 px-4 py-2 text-sm font-semibold text-purple-200">
                <span>✨</span>
                <span>AI Movie Recommendation</span>
            </div>

            <h1 class="max-w-3xl text-5xl font-black leading-tight md:text-7xl">
                Đặt vé xem phim
                <span class="bg-gradient-to-r from-[#FF3D57] to-[#FFB703] bg-clip-text text-transparent">
                    thông minh
                </span>
                cùng AI
            </h1>

            <p class="mt-6 max-w-2xl text-lg leading-8 text-gray-300">
                MovieMate giúp bạn tìm phim phù hợp, chọn rạp, chọn suất chiếu,
                chọn ghế yêu thích và đặt vé nhanh chóng chỉ trong vài bước.
            </p>

            <div class="mt-9 flex flex-wrap gap-4">
                <a href="/movies" class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-8 py-4 font-bold text-white shadow-xl shadow-red-500/30 transition hover:scale-105">
                    Đặt vé ngay
                </a>

                <a href="/ai/recommend" class="rounded-2xl border border-white/10 bg-white/5 px-8 py-4 font-bold text-white backdrop-blur transition hover:border-[#7C3AED] hover:text-purple-300">
                    Hỏi AI gợi ý phim
                </a>
            </div>

            <div class="mt-10 grid max-w-xl grid-cols-3 gap-4">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                    <h3 class="text-2xl font-black">100+</h3>
                    <p class="mt-1 text-sm text-gray-400">Phim hấp dẫn</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                    <h3 class="text-2xl font-black">20+</h3>
                    <p class="mt-1 text-sm text-gray-400">Rạp chiếu</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                    <h3 class="text-2xl font-black">AI</h3>
                    <p class="mt-1 text-sm text-gray-400">Gợi ý chuẩn gu</p>
                </div>
            </div>
        </div>

        <div class="relative hidden lg:block">
            <div class="absolute -left-8 top-12 z-10 rounded-3xl border border-white/10 bg-[#151A27]/80 p-5 shadow-2xl backdrop-blur-xl">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#7C3AED] to-[#2563EB]">
                        ✨
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">AI đề xuất</p>
                        <h4 class="font-bold">Phim phù hợp với bạn</h4>
                    </div>
                </div>
            </div>

            <div class="ml-auto w-[420px] rotate-3 rounded-[32px] border border-white/10 bg-white/10 p-4 shadow-2xl shadow-red-500/20 backdrop-blur">
                <img
                    src="https://images.unsplash.com/photo-1440404653325-ab127d49abc1?q=80&w=900&auto=format&fit=crop"
                    class="h-[560px] w-full rounded-[26px] object-cover"
                    alt="Movie Poster"
                >
            </div>
        </div>
    </div>
</section>

<section class="relative -mt-24 z-20 px-6">
    <div class="mx-auto max-w-6xl rounded-[28px] border border-white/10 bg-[#151A27]/90 p-6 shadow-2xl shadow-purple-500/10 backdrop-blur-xl">
        <div class="grid gap-4 lg:grid-cols-[1fr_auto]">
            <div>
                <h2 class="text-2xl font-black">Bạn muốn xem phim gì hôm nay?</h2>
                <p class="mt-2 text-sm text-gray-400">
                    Nhập sở thích của bạn, MovieMate AI sẽ gợi ý phim phù hợp.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    type="text"
                    placeholder="Tôi thích phim hành động, muốn xem tối nay ở Hà Nội..."
                    class="h-14 w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 text-sm text-white outline-none transition placeholder:text-gray-500 focus:border-[#7C3AED] sm:w-[520px]"
                >

                <a href="/ai/recommend" class="flex h-14 items-center justify-center rounded-2xl bg-gradient-to-r from-[#7C3AED] to-[#2563EB] px-7 font-bold shadow-lg shadow-purple-500/30 transition hover:scale-105">
                    Gợi ý bằng AI
                </a>
            </div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-[1440px] px-6 py-24 lg:px-10">
    <div class="mb-10 flex items-end justify-between">
        <div>
            <p class="mb-2 text-sm font-bold uppercase tracking-[0.3em] text-[#FF7A18]">Now Showing</p>
            <h2 class="text-4xl font-black">Phim đang chiếu</h2>
            <p class="mt-3 text-gray-400">Những bộ phim hot nhất đang được yêu thích.</p>
        </div>

        <a href="/movies" class="hidden rounded-xl border border-white/10 px-5 py-3 text-sm font-bold text-gray-300 transition hover:border-[#FF7A18] hover:text-[#FF7A18] md:block">
            Xem tất cả
        </a>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">

        @foreach (range(1, 5) as $i)
            <div class="group overflow-hidden rounded-[24px] border border-white/10 bg-[#151A27] p-3 transition duration-300 hover:-translate-y-2 hover:border-[#FF7A18]/60 hover:shadow-2xl hover:shadow-red-500/20">
                <div class="relative overflow-hidden rounded-[20px]">
                    <img
                        src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=600&auto=format&fit=crop"
                        class="h-[300px] w-full object-cover transition duration-500 group-hover:scale-110"
                        alt="Movie"
                    >

                    <div class="absolute left-3 top-3 rounded-full bg-green-500 px-3 py-1 text-xs font-bold text-white">
                        Đang chiếu
                    </div>

                    <div class="absolute right-3 top-3 rounded-full bg-black/70 px-3 py-1 text-xs font-bold text-yellow-300">
                        ⭐ 4.8
                    </div>
                </div>

                <div class="p-3">
                    <h3 class="line-clamp-1 text-lg font-black">Biệt Đội Siêu Anh Hùng</h3>
                    <p class="mt-2 text-sm text-gray-400">Hành động, Phiêu lưu</p>

                    <div class="mt-3 flex items-center justify-between text-sm text-gray-400">
                        <span>120 phút</span>
                        <span class="rounded-lg bg-white/10 px-2 py-1 text-xs">T13</span>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <a href="/movies/{{ $i }}" class="rounded-xl border border-white/10 py-3 text-center text-sm font-bold transition hover:border-[#FF7A18] hover:text-[#FF7A18]">
                            Chi tiết
                        </a>

                        <a href="/movies/{{ $i }}#showtimes" class="rounded-xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] py-3 text-center text-sm font-bold transition hover:scale-105">
                            Đặt vé
                        </a>
                    </div>
                </div>
            </div>
        @endforeach

    </div>
</section>

<section class="bg-[#0B0F1A] py-20">
    <div class="mx-auto max-w-[1440px] px-6 lg:px-10">
        <div class="mb-12 text-center">
            <p class="mb-2 text-sm font-bold uppercase tracking-[0.3em] text-[#7C3AED]">Why MovieMate</p>
            <h2 class="text-4xl font-black">Trải nghiệm đặt vé thông minh hơn</h2>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-7">
                <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#7C3AED] to-[#2563EB] text-2xl">
                    ✨
                </div>
                <h3 class="text-xl font-black">AI gợi ý chuẩn gu</h3>
                <p class="mt-3 text-sm leading-6 text-gray-400">
                    Gợi ý phim theo sở thích, tâm trạng và thời gian rảnh của bạn.
                </p>
            </div>

            <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-7">
                <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#FF3D57] to-[#FF7A18] text-2xl">
                    💺
                </div>
                <h3 class="text-xl font-black">Chọn ghế trực quan</h3>
                <p class="mt-3 text-sm leading-6 text-gray-400">
                    Sơ đồ ghế rõ ràng, dễ chọn và hiển thị ghế đã đặt.
                </p>
            </div>

            <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-7">
                <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#22C55E] to-[#16A34A] text-2xl">
                    🎟️
                </div>
                <h3 class="text-xl font-black">QR vé nhanh chóng</h3>
                <p class="mt-3 text-sm leading-6 text-gray-400">
                    Nhận vé điện tử với mã QR để nhân viên kiểm tra dễ dàng.
                </p>
            </div>

            <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-7">
                <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#F59E0B] to-[#FF7A18] text-2xl">
                    📊
                </div>
                <h3 class="text-xl font-black">Quản lý chuyên nghiệp</h3>
                <p class="mt-3 text-sm leading-6 text-gray-400">
                    Admin quản lý phim, rạp, suất chiếu, vé và thống kê doanh thu.
                </p>
            </div>
        </div>
    </div>
</section>

@endsection