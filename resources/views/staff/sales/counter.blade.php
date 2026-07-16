@extends('layouts.staff')

@section('title', 'BÃ¡n vÃ© táº¡i quáº§y - MovieMate')
@section('page-title', 'BÃ¡n vÃ© táº¡i quáº§y')

@section('content')

<div class="grid gap-8 lg:grid-cols-[1fr_380px]">
    <div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
        <h1 class="mb-6 text-3xl font-black">Táº¡o vÃ© táº¡i quáº§y</h1>

        <div class="grid gap-4 md:grid-cols-2">
            <select class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none"><option>Chá»n phim</option></select>
            <select class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none"><option>Chá»n ráº¡p</option></select>
            <select class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none"><option>Chá»n suáº¥t chiáº¿u</option></select>
            <input placeholder="TÃªn khÃ¡ch hÃ ng" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none">
        </div>

        <div class="mt-8">
            <h2 class="mb-4 text-xl font-black">Chá»n gháº¿</h2>
            <div class="grid max-w-lg grid-cols-8 gap-3">
                @foreach (range(1,40) as $i)
                    <button class="h-10 rounded-xl bg-[#374151] text-xs font-bold hover:bg-[#FF3D57]">{{ $i }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="h-fit rounded-[28px] border border-white/10 bg-[#151A27] p-6">
        <h2 class="mb-5 text-2xl font-black">TÃ³m táº¯t bÃ¡n vÃ©</h2>
        <div class="space-y-4 text-sm">
            <div class="flex justify-between"><span class="text-gray-400">Phim</span><span class="font-bold">Thanh GÆ°Æ¡m Diá»‡t Quá»·</span></div>
            <div class="flex justify-between"><span class="text-gray-400">Gháº¿</span><span class="font-bold">E5, E6</span></div>
            <div class="flex justify-between"><span class="text-gray-400">Tá»•ng tiá»n</span><span class="font-bold text-[#FF7A18]">180.000Ä‘</span></div>
        </div>

        <button class="mt-6 w-full rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] py-4 font-bold">
            Táº¡o vÃ©
        </button>
    </div>
</div>

@endsection
