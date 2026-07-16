@extends('layouts.user')

@section('title', 'Danh sách phim - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-12 lg:px-10">

    <div class="mx-auto max-w-[1440px]">

        <div class="mb-10">
            <p class="mb-2 text-sm font-bold uppercase tracking-[0.3em] text-[#FF7A18]">Movies</p>
            <h1 class="text-5xl font-black">Khám phá phim</h1>
            <p class="mt-4 max-w-2xl text-gray-400">
                Tìm kiếm phim đang chiếu, sắp chiếu và nhận gợi ý phim phù hợp với sở thích của bạn.
            </p>
        </div>

        <div class="mb-10 rounded-[24px] border border-white/10 bg-[#151A27] p-5">
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">

                <input
                    type="text"
                    placeholder="Tìm tên phim..."
                    class="h-12 rounded-2xl border border-white/10 bg-[#080A12] px-4 text-sm outline-none placeholder:text-gray-500 focus:border-[#FF7A18] lg:col-span-2"
                >

                <select class="h-12 rounded-2xl border border-white/10 bg-[#080A12] px-4 text-sm outline-none focus:border-[#FF7A18]">
                    <option>Thể loại</option>
                    <option>Hành động</option>
                    <option>Kinh dị</option>
                    <option>Hài</option>
                    <option>Tình cảm</option>
                </select>

                <select class="h-12 rounded-2xl border border-white/10 bg-[#080A12] px-4 text-sm outline-none focus:border-[#FF7A18]">
                    <option>Trạng thái</option>
                    <option>Đang chiếu</option>
                    <option>Sắp chiếu</option>
                    <option>Sắp ra mắt</option>
                </select>

                <select class="h-12 rounded-2xl border border-white/10 bg-[#080A12] px-4 text-sm outline-none focus:border-[#FF7A18]">
                    <option>Quốc gia</option>
                    <option>Việt Nam</option>
                    <option>Mỹ</option>
                    <option>Hàn Quốc</option>
                    <option>Nhật Bản</option>
                </select>

                <button class="h-12 rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] text-sm font-bold">
                    Lọc phim
                </button>
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">

            @foreach (range(1, 15) as $i)
                <div class="group overflow-hidden rounded-[24px] border border-white/10 bg-[#151A27] p-3 transition duration-300 hover:-translate-y-2 hover:border-[#FF7A18]/60 hover:shadow-2xl hover:shadow-red-500/20">

                    <div class="relative overflow-hidden rounded-[20px]">
                        <img
                            src="https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=600&auto=format&fit=crop"
                            class="h-[310px] w-full object-cover transition duration-500 group-hover:scale-110"
                            alt="Movie"
                        >

                        <div class="absolute left-3 top-3 rounded-full bg-green-500 px-3 py-1 text-xs font-bold">
                            Đang chiếu
                        </div>

                        <div class="absolute bottom-3 left-3 rounded-full bg-black/70 px-3 py-1 text-xs font-bold text-yellow-300">
                            ⭐ 4.8
                        </div>
                    </div>

                    <div class="p-3">
                        <h3 class="line-clamp-1 text-lg font-black">
                            Thanh Gươm Diệt Quỷ
                        </h3>

                        <p class="mt-2 text-sm text-gray-400">
                            Hành động, Hoạt hình
                        </p>

                        <div class="mt-3 flex items-center justify-between text-sm text-gray-400">
                            <span>115 phút</span>
                            <span class="rounded-lg bg-white/10 px-2 py-1 text-xs">T16</span>
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

    </div>

</section>

@endsection