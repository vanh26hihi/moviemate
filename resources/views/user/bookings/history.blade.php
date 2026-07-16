@extends('layouts.user')

@section('title', 'Lịch sử đặt vé - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-12 lg:px-10">

    <div class="mx-auto max-w-[1200px]">

        <div class="mb-10">
            <p class="mb-2 text-sm font-bold uppercase tracking-[0.3em] text-[#FF7A18]">History</p>
            <h1 class="text-4xl font-black">Lịch sử đặt vé</h1>
            <p class="mt-3 text-gray-400">Xem lại các vé bạn đã đặt trên MovieMate.</p>
        </div>

        <div class="space-y-5">
            @foreach (range(1, 5) as $i)
                <div class="rounded-[28px] border border-white/10 bg-[#151A27] p-5">
                    <div class="grid gap-5 md:grid-cols-[120px_1fr_auto] md:items-center">

                        <img
                            src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=400&auto=format&fit=crop"
                            class="h-36 w-full rounded-2xl object-cover md:w-28"
                            alt="Poster"
                        >

                        <div>
                            <div class="mb-3 flex flex-wrap gap-2">
                                <span class="rounded-full bg-green-500/20 px-3 py-1 text-xs font-bold text-green-400">
                                    Chưa sử dụng
                                </span>

                                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold">
                                    MMT-2026-000{{ $i }}
                                </span>
                            </div>

                            <h3 class="text-2xl font-black">Thanh Gươm Diệt Quỷ</h3>
                            <p class="mt-2 text-sm text-gray-400">
                                MovieMate Hà Nội • 20:45 - 20/05/2026 • Ghế E5, E6
                            </p>
                            <p class="mt-2 font-bold text-[#FF7A18]">180.000đ</p>
                        </div>

                        <div class="flex gap-3 md:flex-col">
                            <a href="/my-ticket" class="rounded-xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-5 py-3 text-center text-sm font-bold">
                                Xem vé
                            </a>

                            <a href="#" class="rounded-xl border border-white/10 px-5 py-3 text-center text-sm font-bold transition hover:border-[#FF7A18] hover:text-[#FF7A18]">
                                Đánh giá
                            </a>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

    </div>

</section>

@endsection