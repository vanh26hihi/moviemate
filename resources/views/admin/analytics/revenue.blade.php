@extends('layouts.admin')

@section('title', 'Thá»‘ng kÃª doanh thu - MovieMate')
@section('page-title', 'Thá»‘ng kÃª doanh thu')

@section('content')

<div class="grid gap-5 md:grid-cols-3">
    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">Doanh thu hÃ´m nay</p>
        <h3 class="mt-3 text-4xl font-black text-[#FF7A18]">12.5M</h3>
    </div>

    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">Doanh thu thÃ¡ng</p>
        <h3 class="mt-3 text-4xl font-black">128.5M</h3>
    </div>

    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">Tá»· lá»‡ láº¥p Ä‘áº§y</p>
        <h3 class="mt-3 text-4xl font-black text-green-400">72%</h3>
    </div>
</div>

<div class="mt-8 rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <h1 class="mb-6 text-3xl font-black">Biá»ƒu Ä‘á»“ doanh thu</h1>
    <div class="flex h-96 items-end gap-4">
        @foreach ([100,150,120,180,220,190,260,300,280,330,310,360] as $value)
            <div class="flex flex-1 flex-col items-center gap-3">
                <div class="w-full rounded-t-2xl bg-gradient-to-t from-[#FF3D57] to-[#FF7A18]" style="height: {{ $value }}px"></div>
                <span class="text-xs text-gray-400">T{{ $loop->iteration }}</span>
            </div>
        @endforeach
    </div>
</div>

@endsection
