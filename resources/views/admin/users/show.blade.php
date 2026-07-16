@extends('layouts.admin')

@section('title', 'Chi tiáº¿t ngÆ°á»i dÃ¹ng - MovieMate')
@section('page-title', 'Chi tiáº¿t ngÆ°á»i dÃ¹ng')

@section('content')

<div class="mx-auto max-w-4xl rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <div class="mb-8 flex items-center gap-5">
        <div class="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-r from-[#7C3AED] to-[#2563EB] text-4xl font-black">M</div>
        <div>
            <h1 class="text-3xl font-black">Nguyá»…n Máº¡nh</h1>
            <p class="mt-2 text-gray-400">user@example.com</p>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        <div class="rounded-2xl bg-[#080A12] p-5"><p class="text-sm text-gray-400">Sá»‘ vÃ© Ä‘Ã£ Ä‘áº·t</p><p class="text-3xl font-black">12</p></div>
        <div class="rounded-2xl bg-[#080A12] p-5"><p class="text-sm text-gray-400">Tá»•ng chi tiÃªu</p><p class="text-3xl font-black text-[#FF7A18]">1.8M</p></div>
        <div class="rounded-2xl bg-[#080A12] p-5"><p class="text-sm text-gray-400">Vai trÃ²</p><p class="text-3xl font-black">User</p></div>
    </div>
</div>

@endsection
