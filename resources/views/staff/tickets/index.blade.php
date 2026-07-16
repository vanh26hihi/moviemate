@extends('layouts.staff')

@section('title', 'Danh sÃ¡ch vÃ© - MovieMate')
@section('page-title', 'Danh sÃ¡ch vÃ©')

@section('content')

<div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-3xl font-black">Danh sÃ¡ch vÃ© hÃ´m nay</h1>
            <p class="mt-2 text-gray-400">Theo dÃµi tráº¡ng thÃ¡i vÃ© theo suáº¥t chiáº¿u.</p>
        </div>

        <input placeholder="TÃ¬m mÃ£ vÃ©..." class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-3 outline-none focus:border-[#FF7A18]">
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="text-gray-400">
                <tr class="border-b border-white/10">
                    <th class="py-4">MÃ£ vÃ©</th>
                    <th>KhÃ¡ch hÃ ng</th>
                    <th>Phim</th>
                    <th>Suáº¥t chiáº¿u</th>
                    <th>Gháº¿</th>
                    <th>Tráº¡ng thÃ¡i</th>
                    <th>HÃ nh Ä‘á»™ng</th>
                </tr>
            </thead>
            <tbody>
                @foreach (range(1,8) as $i)
                    <tr class="border-b border-white/5">
                        <td class="py-4 font-bold">MMT-2026-000{{ $i }}</td>
                        <td>Nguyá»…n Máº¡nh</td>
                        <td>Thanh GÆ°Æ¡m Diá»‡t Quá»·</td>
                        <td>20:45</td>
                        <td>E5, E6</td>
                        <td><span class="rounded-full bg-green-500/20 px-3 py-1 text-xs font-bold text-green-400">ChÆ°a dÃ¹ng</span></td>
                        <td><a href="/staff/tickets/valid" class="text-[#FF7A18]">Kiá»ƒm tra</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
