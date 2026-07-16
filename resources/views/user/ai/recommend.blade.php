@extends('layouts.user')

@section('title', 'AI gợi ý phim - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-12 lg:px-10">

    <div class="mx-auto max-w-[1200px]">

        <div class="mb-10 text-center">
            <div class="mx-auto mb-5 inline-flex rounded-full border border-[#7C3AED]/40 bg-[#7C3AED]/10 px-5 py-2 text-sm font-bold text-purple-200">
                ✨ MovieMate AI
            </div>

            <h1 class="text-5xl font-black">AI gợi ý phim dành riêng cho bạn</h1>
            <p class="mx-auto mt-4 max-w-2xl text-gray-400">
                Nhập sở thích, tâm trạng và thời gian rảnh, MovieMate AI sẽ gợi ý phim phù hợp nhất.
            </p>
        </div>

        <div class="grid gap-8 lg:grid-cols-[420px_1fr]">

            <div class="rounded-[32px] border border-white/10 bg-[#151A27] p-6">
                <h2 class="mb-5 text-2xl font-black">Thông tin sở thích</h2>

                <form class="space-y-4">
                    <select class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">
                        <option>Thể loại yêu thích</option>
                        <option>Hành động</option>
                        <option>Kinh dị</option>
                        <option>Hài</option>
                        <option>Tình cảm</option>
                    </select>

                    <select class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">
                        <option>Tâm trạng hiện tại</option>
                        <option>Muốn vui vẻ</option>
                        <option>Muốn hồi hộp</option>
                        <option>Muốn thư giãn</option>
                    </select>

                    <input placeholder="Thời gian muốn xem, ví dụ: tối nay" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">

                    <input placeholder="Khu vực/rạp mong muốn" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">

                    <select class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">
                        <option>Bạn đi xem với ai?</option>
                        <option>Một mình</option>
                        <option>Bạn bè</option>
                        <option>Người yêu</option>
                        <option>Gia đình</option>
                    </select>

                    <button type="button" class="w-full rounded-2xl bg-gradient-to-r from-[#7C3AED] to-[#2563EB] py-4 font-bold shadow-xl shadow-purple-500/30 transition hover:scale-105">
                        Gợi ý phim bằng AI
                    </button>
                </form>
            </div>

            <div class="space-y-5">
                <div class="rounded-[32px] border border-[#7C3AED]/30 bg-[#151A27] p-6 shadow-2xl shadow-purple-500/10">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#7C3AED] to-[#2563EB]">
                            ✨
                        </div>
                        <div>
                            <h2 class="text-2xl font-black">Kết quả AI đề xuất</h2>
                            <p class="text-sm text-gray-400">Dựa trên sở thích của bạn</p>
                        </div>
                    </div>

                    @foreach (range(1, 3) as $i)
                        <div class="mb-5 rounded-[24px] border border-white/10 bg-[#080A12] p-5 last:mb-0">
                            <div class="grid gap-5 md:grid-cols-[120px_1fr_auto] md:items-center">
                                <img
                                    src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=400&auto=format&fit=crop"
                                    class="h-40 w-full rounded-2xl object-cover md:w-28"
                                    alt="Poster"
                                >

                                <div>
                                    <h3 class="text-xl font-black">Thanh Gươm Diệt Quỷ</h3>
                                    <p class="mt-2 text-sm text-gray-400">Hành động, Hoạt hình • 115 phút</p>
                                    <p class="mt-3 text-sm leading-6 text-purple-200">
                                        AI gợi ý phim này vì bạn thích hành động, muốn xem buổi tối và phim có suất chiếu phù hợp tại Hà Nội.
                                    </p>
                                </div>

                                <a href="/movies/{{ $i }}" class="rounded-xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-5 py-3 text-center text-sm font-bold">
                                    Đặt vé
                                </a>
                            </div>
                        </div>
                    @endforeach

                </div>
            </div>

        </div>

    </div>

</section>

@endsection