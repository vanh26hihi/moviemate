@extends('layouts.user')

@section('title', 'AI Chatbot - MovieMate')

@section('content')

<section class="min-h-screen bg-[#080A12] px-6 py-12 lg:px-10">

    <div class="mx-auto grid max-w-[1200px] overflow-hidden rounded-[32px] border border-white/10 bg-[#151A27] lg:grid-cols-[300px_1fr]">

        <aside class="border-b border-white/10 bg-[#0B0F1A] p-5 lg:border-b-0 lg:border-r">
            <h2 class="text-xl font-black">MovieMate AI</h2>
            <p class="mt-2 text-sm text-gray-400">Chatbot hỗ trợ khách hàng</p>

            <div class="mt-6 space-y-3">
                <button class="w-full rounded-2xl bg-[#151A27] p-4 text-left text-sm font-bold transition hover:bg-white/10">
                    Hôm nay có phim gì hay?
                </button>

                <button class="w-full rounded-2xl bg-[#151A27] p-4 text-left text-sm font-bold transition hover:bg-white/10">
                    Tôi muốn xem phim hành động
                </button>

                <button class="w-full rounded-2xl bg-[#151A27] p-4 text-left text-sm font-bold transition hover:bg-white/10">
                    Cách đặt vé như thế nào?
                </button>
            </div>
        </aside>

        <div class="flex min-h-[720px] flex-col">

            <div class="border-b border-white/10 p-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#7C3AED] to-[#2563EB]">
                        ✨
                    </div>
                    <div>
                        <h1 class="text-xl font-black">AI Chatbot</h1>
                        <p class="text-sm text-green-400">Đang hoạt động</p>
                    </div>
                </div>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto p-6">
                <div class="max-w-[75%] rounded-3xl border border-white/10 bg-[#080A12] p-5">
                    <p class="text-sm leading-6 text-gray-200">
                        Xin chào! Mình là MovieMate AI. Bạn muốn tìm phim, lịch chiếu hay hướng dẫn đặt vé?
                    </p>
                </div>

                <div class="ml-auto max-w-[75%] rounded-3xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] p-5">
                    <p class="text-sm leading-6">
                        Tôi thích phim hành động, tối nay nên xem phim gì?
                    </p>
                </div>

                <div class="max-w-[75%] rounded-3xl border border-[#7C3AED]/30 bg-[#080A12] p-5">
                    <p class="text-sm leading-6 text-gray-200">
                        Bạn có thể xem “Thanh Gươm Diệt Quỷ” vì phim thuộc thể loại hành động,
                        có suất chiếu lúc 20:45 tại MovieMate Hà Nội và còn vé.
                    </p>
                </div>
            </div>

            <div class="border-t border-white/10 p-5">
                <div class="flex gap-3">
                    <input
                        placeholder="Nhập câu hỏi của bạn..."
                        class="flex-1 rounded-2xl border border-white/10 bg-[#080A12] px-5 py-4 outline-none focus:border-[#7C3AED]"
                    >

                    <button class="rounded-2xl bg-gradient-to-r from-[#7C3AED] to-[#2563EB] px-7 font-bold">
                        Gửi
                    </button>
                </div>
            </div>

        </div>

    </div>

</section>

@endsection