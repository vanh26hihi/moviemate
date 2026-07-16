@extends('layouts.admin')

@section('title', 'Phim bÃ¡n cháº¡y - MovieMate')
@section('page-title', 'Phim bÃ¡n cháº¡y')

@section('content')

<div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <h1 class="mb-6 text-3xl font-black">Top phim bÃ¡n cháº¡y</h1>

    <div class="grid gap-5 md:grid-cols-3">
        @foreach (range(1,3) as $i)
            <div class="rounded-[24px] border border-white/10 bg-[#080A12] p-5">
                <div class="mb-4 text-4xl font-black text-[#FF7A18]">#{{ $i }}</div>
                <img src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=400&auto=format&fit=crop" class="h-64 w-full rounded-2xl object-cover">
                <h3 class="mt-4 text-xl font-black">Thanh GÆ°Æ¡m Diá»‡t Quá»·</h3>
                <p class="mt-2 text-sm text-gray-400">{{ 800 - $i * 100 }} vÃ© â€¢ {{ 80 - $i * 10 }}M doanh thu</p>
            </div>
        @endforeach
    </div>
</div>

@endsection
