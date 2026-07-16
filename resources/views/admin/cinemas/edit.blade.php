@extends('layouts.admin')

@section('title', 'Form quáº£n lÃ½ - MovieMate')
@section('page-title', 'ThÃªm/Sá»­a dá»¯ liá»‡u')

@section('content')

<div class="mx-auto max-w-4xl rounded-[28px] border border-white/10 bg-[#151A27] p-6">
    <h1 class="text-3xl font-black">Form nháº­p dá»¯ liá»‡u</h1>
    <p class="mt-2 text-gray-400">Giao diá»‡n form máº«u cho chá»©c nÄƒng quáº£n trá»‹.</p>

    <form class="mt-8 grid gap-5 md:grid-cols-2">
        <input placeholder="TÃªn" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
        <input placeholder="MÃ£ / slug" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
        <input placeholder="ThÃ´ng tin phá»¥" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]">
        <select class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18]"><option>Tráº¡ng thÃ¡i hoáº¡t Ä‘á»™ng</option></select>
        <textarea rows="6" placeholder="MÃ´ táº£" class="rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#FF7A18] md:col-span-2"></textarea>

        <div class="flex gap-4 md:col-span-2">
            <button type="button" class="rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-8 py-4 font-bold">LÆ°u dá»¯ liá»‡u</button>
            <a href="/admin/dashboard" class="rounded-2xl border border-white/10 px-8 py-4 font-bold">Há»§y</a>
        </div>
    </form>
</div>

@endsection
