@extends('layouts.user')

@section('title', 'ÄÄƒng nháº­p Admin - MovieMate')

@section('content')

<section class="flex min-h-screen items-center justify-center bg-[#080A12] px-6 py-16">
    <div class="w-full max-w-md rounded-[32px] border border-white/10 bg-[#151A27] p-8">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-r from-[#7C3AED] to-[#2563EB] text-3xl">âš™ï¸</div>
            <h1 class="text-3xl font-black">MovieMate Admin</h1>
            <p class="mt-2 text-gray-400">Quáº£n lÃ½ há»‡ thá»‘ng Ä‘áº·t vÃ© xem phim.</p>
        </div>

        <form class="space-y-5">
            <input placeholder="Email admin" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">
            <input placeholder="Máº­t kháº©u" type="password" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]">
            <a href="/admin/dashboard" class="block rounded-2xl bg-gradient-to-r from-[#7C3AED] to-[#2563EB] py-4 text-center font-bold">ÄÄƒng nháº­p</a>
        </form>
    </div>
</section>

@endsection
