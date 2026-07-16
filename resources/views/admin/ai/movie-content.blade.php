@extends('layouts.admin')

@section('title', 'AI táº¡o mÃ´ táº£ phim - MovieMate')
@section('page-title', 'AI táº¡o mÃ´ táº£ phim')

@section('content')

<div class="grid gap-8 lg:grid-cols-[420px_1fr]">
    <div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
        <div class="mb-6 flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#7C3AED] to-[#2563EB]">âœ¨</div>
            <div>
                <h1 class="text-2xl font-black">AI Content Generator</h1>
                <p class="text-sm text-gray-400">Táº¡o mÃ´ táº£ phim báº±ng AI</p>
            </div>
        </div>

        <form class="space-y-4">
            <input placeholder="TÃªn phim" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">
            <input placeholder="Thá»ƒ loáº¡i" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">

            <select class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">
                <option>Tone ná»™i dung</option>
                <option>Háº¥p dáº«n</option>
                <option>Ngáº¯n gá»n</option>
                <option>Chuáº©n SEO</option>
                <option>Quáº£ng cÃ¡o</option>
            </select>

            <textarea rows="8" placeholder="Ná»™i dung gá»‘c..." class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]"></textarea>

            <button type="button" class="w-full rounded-2xl bg-gradient-to-r from-[#7C3AED] to-[#2563EB] py-4 font-bold shadow-xl shadow-purple-500/30">
                Táº¡o ná»™i dung báº±ng AI
            </button>
        </form>
    </div>

    <div class="rounded-[28px] border border-[#7C3AED]/30 bg-[#151A27] p-6 shadow-2xl shadow-purple-500/10">
        <h2 class="mb-6 text-2xl font-black">Káº¿t quáº£ AI</h2>

        <div class="space-y-5">
            <div class="rounded-2xl bg-[#080A12] p-5">
                <p class="mb-2 text-sm font-bold text-[#7C3AED]">MÃ´ táº£ ngáº¯n</p>
                <p class="leading-7 text-gray-300">Má»™t bá»™ phim hÃ nh Ä‘á»™ng ká»‹ch tÃ­nh vá»›i hÃ¬nh áº£nh mÃ£n nhÃ£n, cÃ¢u chuyá»‡n cáº£m xÃºc vÃ  nhá»¯ng tráº­n chiáº¿n ngháº¹t thá»Ÿ.</p>
            </div>

            <div class="rounded-2xl bg-[#080A12] p-5">
                <p class="mb-2 text-sm font-bold text-[#7C3AED]">MÃ´ táº£ SEO</p>
                <p class="leading-7 text-gray-300">Äáº·t vÃ© xem phim Thanh GÆ°Æ¡m Diá»‡t Quá»· táº¡i MovieMate, chá»n gháº¿ nhanh chÃ³ng, xem lá»‹ch chiáº¿u má»›i nháº¥t vÃ  nháº­n gá»£i Ã½ phim tá»« AI.</p>
            </div>

            <div class="rounded-2xl bg-[#080A12] p-5">
                <p class="mb-2 text-sm font-bold text-[#7C3AED]">Caption quáº£ng cÃ¡o</p>
                <p class="leading-7 text-gray-300">Tá»‘i nay xem gÃ¬? Äá»ƒ MovieMate AI gá»£i Ã½ cho báº¡n bá»™ phim hÃ nh Ä‘á»™ng Ä‘ang hot nháº¥t tuáº§n nÃ y!</p>
            </div>
        </div>
    </div>
</div>

@endsection
