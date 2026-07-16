@extends('layouts.admin')

@section('title', 'Quáº£n lÃ½ phim - MovieMate')
@section('page-title', 'Quáº£n lÃ½ phim')

@section('content')

<div class="rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-3xl font-black">Quáº£n lÃ½ phim</h1>
            <p class="mt-2 text-gray-400">Danh sÃ¡ch phim trong há»‡ thá»‘ng</p>
        </div>

        <a href="/admin/movies/create" class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-5 py-3 text-sm font-bold">
            ThÃªm phim
        </a>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <input placeholder="TÃ¬m kiáº¿m..." class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-3 outline-none focus:border-[#FF7A18] md:col-span-2">
        <select class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-3 outline-none focus:border-[#FF7A18]">
            <option>Tráº¡ng thÃ¡i</option>
            <option>Äang hoáº¡t Ä‘á»™ng</option>
            <option>Táº¡m khÃ³a</option>
        </select>
        <button class="rounded-2xl border border-white/10 px-5 py-3 font-bold hover:border-[#FF7A18]">Lá»c</button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="text-gray-400">
                <tr class="border-b border-white/10">
                    <th class="py-4">#</th>
                    <th>TÃªn</th>
                    <th>ThÃ´ng tin</th>
                    <th>NgÃ y táº¡o</th>
                    <th>Tráº¡ng thÃ¡i</th>
                    <th class="text-right">HÃ nh Ä‘á»™ng</th>
                </tr>
            </thead>
            <tbody>
                @foreach (range(1,8) as $i)
                    <tr class="border-b border-white/5">
                        <td class="py-4 font-bold">{{ $i }}</td>
                        <td class="font-bold">Dá»¯ liá»‡u máº«u {{ $i }}</td>
                        <td class="text-gray-400">ThÃ´ng tin chi tiáº¿t cá»§a báº£n ghi {{ $i }}</td>
                        <td>20/05/2026</td>
                        <td><span class="rounded-full bg-green-500/20 px-3 py-1 text-xs font-bold text-green-400">Hoáº¡t Ä‘á»™ng</span></td>
                        <td class="text-right">
                            <a href="#" class="mr-3 text-[#FF7A18]">Sá»­a</a>
                            <a href="#" class="text-red-400">XÃ³a</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
