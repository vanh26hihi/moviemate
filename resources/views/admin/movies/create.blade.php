@extends('layouts.admin')

@section('title', 'Form phim - MovieMate')
@section('page-title', 'ThÃªm/Sá»­a phim')

@section('content')

<div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <div class="mb-8">
        <h1 class="text-3xl font-black">ThÃ´ng tin phim</h1>
        <p class="mt-2 text-gray-400">Nháº­p thÃ´ng tin phim, poster, trailer vÃ  mÃ´ táº£.</p>
    </div>

    <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
        <form class="space-y-5">
            <div class="grid gap-4 md:grid-cols-2">
                <input placeholder="TÃªn phim" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
                <input placeholder="Slug" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
                <select class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]"><option>Thá»ƒ loáº¡i</option></select>
                <input placeholder="Quá»‘c gia" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
                <input placeholder="Thá»i lÆ°á»£ng" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
                <select class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]"><option>Äá»™ tuá»•i</option><option>P</option><option>T13</option><option>T16</option><option>T18</option></select>
                <input type="date" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
                <select class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]"><option>Äang chiáº¿u</option><option>Sáº¯p chiáº¿u</option><option>Ngá»«ng chiáº¿u</option></select>
            </div>

            <input placeholder="Trailer URL" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">

            <textarea rows="8" placeholder="MÃ´ táº£ phim" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]"></textarea>

            <div class="flex flex-wrap gap-4">
                <button type="button" class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-8 py-4 font-bold">LÆ°u phim</button>
                <a href="/admin/ai/movie-content" class="rounded-2xl bg-gradient-to-r from-[#7C3AED] to-[#2563EB] px-8 py-4 font-bold">Táº¡o mÃ´ táº£ báº±ng AI</a>
                <a href="/admin/movies" class="rounded-2xl border border-white/10 px-8 py-4 font-bold">Há»§y</a>
            </div>
        </form>

        <div class="space-y-5">
            <div class="rounded-[24px] border border-white/10 bg-[#080A12] p-5">
                <h2 class="mb-4 font-black">Poster phim</h2>
                <div class="flex h-80 items-center justify-center rounded-2xl border-2 border-dashed border-white/10 text-gray-400">Upload poster</div>
            </div>

            <div class="rounded-[24px] border border-white/10 bg-[#080A12] p-5">
                <h2 class="mb-4 font-black">Banner phim</h2>
                <div class="flex h-40 items-center justify-center rounded-2xl border-2 border-dashed border-white/10 text-gray-400">Upload banner</div>
            </div>
        </div>
    </div>
</div>

@endsection
