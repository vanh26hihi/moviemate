@extends('layouts.user')

@section('title', 'Hồ sơ cá nhân - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-12">

    <div class="mx-auto max-w-4xl">

        <div class="rounded-[32px] border border-white/10 bg-[#151A27] p-8">

            <div class="mb-8 flex items-center gap-5">
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-[#FF3D57] to-[#FF7A18] text-4xl font-black">
                    M
                </div>

                <div>
                    <h1 class="text-3xl font-black">Nguyễn Mạnh</h1>
                    <p class="mt-2 text-gray-400">user@example.com</p>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-bold">Họ và tên</label>
                    <input value="Nguyễn Mạnh" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-bold">Email</label>
                    <input value="user@example.com" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-bold">Số điện thoại</label>
                    <input value="0987654321" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-bold">Thành phố</label>
                    <input value="Hà Nội" class="w-full rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none">
                </div>
            </div>

            <button class="mt-8 rounded-2xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-8 py-4 font-bold">
                Cập nhật hồ sơ
            </button>

        </div>

    </div>

</section>

@endsection
