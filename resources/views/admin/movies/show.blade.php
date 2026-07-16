@extends('layouts.admin')

@section('title', 'Chi tiáº¿t phim - MovieMate')
@section('page-title', 'Chi tiáº¿t phim')

@section('content')

<div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
        <img src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=600&auto=format&fit=crop" class="h-[420px] w-full rounded-3xl object-cover">

        <div>
            <span class="rounded-full bg-green-500/20 px-4 py-2 text-sm font-bold text-green-400">Äang chiáº¿u</span>
            <h1 class="mt-5 text-5xl font-black">Thanh GÆ°Æ¡m Diá»‡t Quá»·</h1>
            <p class="mt-5 max-w-3xl leading-8 text-gray-300">
                MÃ´ táº£ phim máº«u dÃ¹ng cho trang quáº£n trá»‹. Admin cÃ³ thá»ƒ xem thÃ´ng tin chi tiáº¿t, chá»‰nh sá»­a hoáº·c táº¡o mÃ´ táº£ báº±ng AI.
            </p>

            <div class="mt-8 grid gap-4 md:grid-cols-4">
                <div class="rounded-2xl bg-[#080A12] p-4"><p class="text-sm text-gray-400">Thá»ƒ loáº¡i</p><p class="font-bold">HÃ nh Ä‘á»™ng</p></div>
                <div class="rounded-2xl bg-[#080A12] p-4"><p class="text-sm text-gray-400">Thá»i lÆ°á»£ng</p><p class="font-bold">115 phÃºt</p></div>
                <div class="rounded-2xl bg-[#080A12] p-4"><p class="text-sm text-gray-400">VÃ© bÃ¡n</p><p class="font-bold">520</p></div>
                <div class="rounded-2xl bg-[#080A12] p-4"><p class="text-sm text-gray-400">Doanh thu</p><p class="font-bold text-[#FF7A18]">46.8M</p></div>
            </div>

            <div class="mt-8 flex gap-4">
                <a href="/admin/movies/1/edit" class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-8 py-4 font-bold">Sá»­a phim</a>
                <a href="/admin/movies" class="rounded-2xl border border-white/10 px-8 py-4 font-bold">Quay láº¡i</a>
            </div>
        </div>
    </div>
</div>

@endsection
