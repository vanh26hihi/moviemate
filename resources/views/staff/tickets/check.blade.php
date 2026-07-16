@extends('layouts.staff')

@section('title', 'Kiá»ƒm tra vÃ© - MovieMate')
@section('page-title', 'Kiá»ƒm tra vÃ© QR')

@section('content')

<div class="grid gap-8 lg:grid-cols-[1fr_420px]">
    <div class="rounded-[32px] border border-white/10 bg-[#151A27] p-8">
        <h1 class="text-3xl font-black">QuÃ©t mÃ£ QR vÃ©</h1>
        <p class="mt-3 text-gray-400">ÄÆ°a mÃ£ QR vÃ o khung quÃ©t hoáº·c nháº­p mÃ£ vÃ© thá»§ cÃ´ng.</p>

        <div class="mt-8 flex min-h-[420px] items-center justify-center rounded-[32px] border-2 border-dashed border-white/10 bg-[#080A12]">
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-24 w-24 items-center justify-center rounded-3xl bg-white/10 text-5xl">â–£</div>
                <p class="font-bold">Khung quÃ©t QR</p>
                <p class="mt-2 text-sm text-gray-400">Demo giao diá»‡n scanner</p>
            </div>
        </div>
    </div>

    <div class="h-fit rounded-[32px] border border-white/10 bg-[#151A27] p-6">
        <h2 class="mb-5 text-2xl font-black">Nháº­p mÃ£ vÃ©</h2>
        <input placeholder="VÃ­ dá»¥: MMT-2026-0001" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">

        <div class="mt-5 grid gap-3">
            <a href="/staff/tickets/valid" class="rounded-2xl bg-gradient-to-r from-[#22C55E] to-[#16A34A] py-4 text-center font-bold">Demo vÃ© há»£p lá»‡</a>
            <a href="/staff/tickets/used" class="rounded-2xl bg-gradient-to-r from-[#F59E0B] to-[#FF7A18] py-4 text-center font-bold">Demo vÃ© Ä‘Ã£ dÃ¹ng</a>
            <a href="/staff/tickets/not-found" class="rounded-2xl bg-gradient-to-r from-[#EF4444] to-[#B91C1C] py-4 text-center font-bold">Demo vÃ© khÃ´ng tá»“n táº¡i</a>
        </div>
    </div>
</div>

@endsection
