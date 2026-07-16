@extends('layouts.staff')

@section('title', 'Staff Dashboard - MovieMate')
@section('page-title', 'Staff Dashboard')

@section('content')

<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">VÃ© hÃ´m nay</p>
        <h3 class="mt-3 text-4xl font-black">248</h3>
    </div>

    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">ÄÃ£ check-in</p>
        <h3 class="mt-3 text-4xl font-black text-green-400">168</h3>
    </div>

    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">ChÆ°a sá»­ dá»¥ng</p>
        <h3 class="mt-3 text-4xl font-black text-yellow-400">80</h3>
    </div>

    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">Suáº¥t chiáº¿u hÃ´m nay</p>
        <h3 class="mt-3 text-4xl font-black text-[#FF7A18]">32</h3>
    </div>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
        <h2 class="mb-5 text-2xl font-black">Thao tÃ¡c nhanh</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <a href="/staff/tickets/check" class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] p-6 font-bold">Kiá»ƒm tra vÃ© QR</a>
            <a href="/staff/counter-sale" class="rounded-2xl bg-gradient-to-r from-[#7C3AED] to-[#2563EB] p-6 font-bold">BÃ¡n vÃ© táº¡i quáº§y</a>
        </div>
    </div>

    <div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
        <h2 class="mb-5 text-2xl font-black">Suáº¥t chiáº¿u gáº§n nháº¥t</h2>
        <div class="space-y-4">
            @foreach (range(1,4) as $i)
                <div class="flex items-center justify-between rounded-2xl bg-[#080A12] p-4">
                    <div>
                        <p class="font-bold">Thanh GÆ°Æ¡m Diá»‡t Quá»·</p>
                        <p class="text-sm text-gray-400">Room 0{{ $i }} â€¢ 20:45</p>
                    </div>
                    <span class="rounded-full bg-green-500/20 px-3 py-1 text-xs font-bold text-green-400">CÃ²n vÃ©</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

@endsection
