@extends('layouts.staff')

@section('title', 'VÃ© Ä‘Ã£ dÃ¹ng - MovieMate')
@section('page-title', 'Káº¿t quáº£ kiá»ƒm tra vÃ©')

@section('content')

<div class="mx-auto max-w-3xl rounded-[32px] border border-yellow-500/30 bg-yellow-500/10 p-8 text-center">
    <div class="mx-auto mb-5 flex h-24 w-24 items-center justify-center rounded-full bg-yellow-500/20 text-5xl text-yellow-400">!</div>
    <h1 class="text-4xl font-black text-yellow-400">VÃ© Ä‘Ã£ Ä‘Æ°á»£c sá»­ dá»¥ng</h1>
    <p class="mt-3 text-gray-300">VÃ© nÃ y Ä‘Ã£ check-in trÆ°á»›c Ä‘Ã³, khÃ´ng thá»ƒ sá»­ dá»¥ng láº¡i.</p>

    <div class="mt-8 rounded-[24px] border border-white/10 bg-[#080A12] p-6 text-left">
        <div class="grid gap-4 md:grid-cols-2">
            <div><p class="text-sm text-gray-400">MÃ£ vÃ©</p><p class="font-bold">MMT-2026-0001</p></div>
            <div><p class="text-sm text-gray-400">Thá»i gian sá»­ dá»¥ng</p><p class="font-bold">20/05/2026 20:10</p></div>
        </div>
    </div>

    <a href="/staff/tickets/check" class="mt-8 inline-block rounded-2xl border border-white/10 px-8 py-4 font-bold hover:border-[#FF7A18]">
        Kiá»ƒm tra vÃ© khÃ¡c
    </a>
</div>

@endsection
