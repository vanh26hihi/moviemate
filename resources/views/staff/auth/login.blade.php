@extends('layouts.user')

@section('title', 'ÄÄƒng nháº­p Staff - MovieMate')

@section('content')

<section class="flex min-h-screen items-center justify-center bg-[#080A12] px-6 py-16">
    <div class="w-full max-w-md rounded-[32px] border border-white/10 bg-[#151A27] p-8">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] text-3xl">ðŸŽŸï¸</div>
            <h1 class="text-3xl font-black">ÄÄƒng nháº­p Staff</h1>
            <p class="mt-2 text-gray-400">Kiá»ƒm tra vÃ© vÃ  há»— trá»£ khÃ¡ch hÃ ng táº¡i ráº¡p.</p>
        </div>

        <form class="space-y-5">
            <input placeholder="Email nhÃ¢n viÃªn" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
            <input placeholder="Máº­t kháº©u" type="password" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
            <a href="/staff/dashboard" class="block rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] py-4 text-center font-bold">ÄÄƒng nháº­p</a>
        </form>
    </div>
</section>

@endsection
