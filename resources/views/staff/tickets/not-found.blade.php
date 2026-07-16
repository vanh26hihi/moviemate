@extends('layouts.staff')

@section('title', 'KhÃ´ng tÃ¬m tháº¥y vÃ© - MovieMate')
@section('page-title', 'Káº¿t quáº£ kiá»ƒm tra vÃ©')

@section('content')

<div class="mx-auto max-w-3xl rounded-[32px] border border-red-500/30 bg-red-500/10 p-8 text-center">
    <div class="mx-auto mb-5 flex h-24 w-24 items-center justify-center rounded-full bg-red-500/20 text-5xl text-red-400">Ã—</div>
    <h1 class="text-4xl font-black text-red-400">KhÃ´ng tÃ¬m tháº¥y vÃ©</h1>
    <p class="mt-3 text-gray-300">MÃ£ vÃ© khÃ´ng tá»“n táº¡i hoáº·c Ä‘Ã£ bá»‹ há»§y.</p>

    <a href="/staff/tickets/check" class="mt-8 inline-block rounded-2xl bg-gradient-to-r from-[#EF4444] to-[#B91C1C] px-8 py-4 font-bold">
        Kiá»ƒm tra láº¡i
    </a>
</div>

@endsection
