@extends('layouts.admin')

@section('title', 'Quáº£n lÃ½ gháº¿ - MovieMate')
@section('page-title', 'Quáº£n lÃ½ gháº¿')

@section('content')

<div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-3xl font-black">Quáº£n lÃ½ gháº¿</h1>
            <p class="mt-2 text-gray-400">Chá»n ráº¡p vÃ  phÃ²ng Ä‘á»ƒ quáº£n lÃ½ sÆ¡ Ä‘á»“ gháº¿.</p>
        </div>

        <a href="/admin/seats/manage" class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-5 py-3 text-sm font-bold">Quáº£n lÃ½ sÆ¡ Ä‘á»“ gháº¿</a>
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        @foreach (range(1,6) as $i)
            <a href="/admin/seats/manage" class="rounded-[24px] border border-white/10 bg-[#080A12] p-6 hover:border-[#FF7A18]">
                <h3 class="text-xl font-black">Room 0{{ $i }}</h3>
                <p class="mt-2 text-sm text-gray-400">MovieMate HÃ  Ná»™i</p>
                <p class="mt-4 text-3xl font-black text-[#FF7A18]">{{ 80 + $i * 10 }} gháº¿</p>
            </a>
        @endforeach
    </div>
</div>

@endsection
