@extends('layouts.admin')

@section('title', 'SÆ¡ Ä‘á»“ gháº¿ - MovieMate')
@section('page-title', 'SÆ¡ Ä‘á»“ gháº¿')

@section('content')

<div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <div class="mb-8">
        <h1 class="text-3xl font-black">Quáº£n lÃ½ sÆ¡ Ä‘á»“ gháº¿ Room 01</h1>
        <p class="mt-2 text-gray-400">Click vÃ o gháº¿ Ä‘á»ƒ Ä‘á»•i loáº¡i gháº¿ hoáº·c tráº¡ng thÃ¡i báº£o trÃ¬.</p>
    </div>

    <div class="mb-10 text-center">
        <div class="mx-auto h-3 max-w-2xl rounded-full bg-gradient-to-r from-transparent via-white to-transparent"></div>
        <p class="mt-4 text-sm font-bold uppercase tracking-[0.4em] text-gray-400">MÃ n hÃ¬nh</p>
    </div>

    <div class="mx-auto max-w-4xl space-y-4">
        @foreach (['A','B','C','D','E','F','G','H'] as $row)
            <div class="flex items-center justify-center gap-3">
                <span class="w-6 text-center text-sm font-bold text-gray-400">{{ $row }}</span>
                @for ($i = 1; $i <= 12; $i++)
                    <button class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#374151] text-xs font-bold hover:bg-[#FF3D57]">{{ $i }}</button>
                @endfor
            </div>
        @endforeach
    </div>

    <div class="mt-10 flex flex-wrap justify-center gap-4">
        <button class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-8 py-4 font-bold">LÆ°u sÆ¡ Ä‘á»“ gháº¿</button>
        <button class="rounded-2xl border border-white/10 px-8 py-4 font-bold">Táº¡o gháº¿ tá»± Ä‘á»™ng</button>
    </div>
</div>

@endsection
