@extends('layouts.admin')

@section('title', 'Admin Dashboard - MovieMate')
@section('page-title', 'Admin Dashboard')

@section('content')

<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">Tá»•ng doanh thu</p>
        <h3 class="mt-3 text-4xl font-black text-[#FF7A18]">128.5M</h3>
    </div>

    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">VÃ© Ä‘Ã£ bÃ¡n</p>
        <h3 class="mt-3 text-4xl font-black">2,485</h3>
    </div>

    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">NgÆ°á»i dÃ¹ng</p>
        <h3 class="mt-3 text-4xl font-black">1,240</h3>
    </div>

    <div class="rounded-[24px] border border-white/10 bg-[#151A27] p-6">
        <p class="text-sm text-gray-400">Phim Ä‘ang chiáº¿u</p>
        <h3 class="mt-3 text-4xl font-black text-green-400">32</h3>
    </div>
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-[1fr_420px]">
    <div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
        <h2 class="mb-6 text-2xl font-black">Doanh thu 7 ngÃ y gáº§n nháº¥t</h2>
        <div class="flex h-80 items-end gap-4">
            @foreach ([45,70,55,90,120,88,135] as $value)
                <div class="flex flex-1 flex-col items-center gap-3">
                    <div class="w-full rounded-t-2xl bg-gradient-to-t from-[#FF3D57] to-[#FF7A18]" style="height: {{ $value * 2 }}px"></div>
                    <span class="text-xs text-gray-400">T{{ $loop->iteration }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
        <h2 class="mb-6 text-2xl font-black">Phim bÃ¡n cháº¡y</h2>
        <div class="space-y-4">
            @foreach (range(1,5) as $i)
                <div class="flex items-center justify-between rounded-2xl bg-[#080A12] p-4">
                    <div>
                        <p class="font-bold">Thanh GÆ°Æ¡m Diá»‡t Quá»·</p>
                        <p class="text-sm text-gray-400">{{ 500 - $i * 50 }} vÃ© Ä‘Ã£ bÃ¡n</p>
                    </div>
                    <span class="font-black text-[#FF7A18]">#{{ $i }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="mt-8 rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <h2 class="mb-6 text-2xl font-black">ÄÆ¡n Ä‘áº·t vÃ© gáº§n Ä‘Ã¢y</h2>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="text-gray-400">
                <tr class="border-b border-white/10">
                    <th class="py-4">MÃ£ vÃ©</th>
                    <th>KhÃ¡ch hÃ ng</th>
                    <th>Phim</th>
                    <th>Ráº¡p</th>
                    <th>Tá»•ng tiá»n</th>
                    <th>Tráº¡ng thÃ¡i</th>
                </tr>
            </thead>
            <tbody>
                @foreach (range(1,6) as $i)
                    <tr class="border-b border-white/5">
                        <td class="py-4 font-bold">MMT-2026-000{{ $i }}</td>
                        <td>Nguyá»…n Máº¡nh</td>
                        <td>Thanh GÆ°Æ¡m Diá»‡t Quá»·</td>
                        <td>MovieMate HÃ  Ná»™i</td>
                        <td class="font-bold text-[#FF7A18]">180.000Ä‘</td>
                        <td><span class="rounded-full bg-green-500/20 px-3 py-1 text-xs font-bold text-green-400">ÄÃ£ Ä‘áº·t</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
