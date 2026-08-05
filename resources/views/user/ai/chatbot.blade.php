@extends('layouts.user')

@section('title', 'Trợ lý trò chuyện AI - MovieMate')

@section('content')
<section class="cinema-surface min-h-screen py-10">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="cinema-card overflow-hidden rounded-3xl">
            <div class="border-b app-border p-6 sm:p-8"><div class="flex items-center gap-4"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-ai-start to-ai-end text-white"><i class="ph-fill ph-robot text-2xl"></i></span><div><h1 class="text-2xl font-extrabold app-text">MovieMate AI</h1><p class="mt-1 text-sm app-muted">Trợ lý tìm phim và lịch chiếu</p></div></div></div>
            <div class="p-6 sm:p-8"><div class="rounded-3xl border border-ai-start/20 bg-ai-start/5 p-6 text-center"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-ai-start/10 text-ai-start"><i class="ph-fill ph-chat-circle-dots text-3xl"></i></div><h2 class="mt-4 text-xl font-extrabold app-text">Trợ lý trò chuyện đang được hoàn thiện</h2><p class="mx-auto mt-2 max-w-lg app-muted">Khung trò chuyện sẽ sẵn sàng khi dịch vụ AI được kết nối.</p><a href="{{ route('user.ai.recommend') }}" class="btn-secondary mt-6"><i class="ph-fill ph-sparkle"></i> Mở AI gợi ý</a></div></div>
            <div class="border-t app-border p-4 sm:p-6"><div class="flex gap-3"><input type="text" disabled class="min-w-0 flex-1 rounded-2xl border app-border app-input px-4 py-3 opacity-70" placeholder="Trợ lý trò chuyện sẽ sẵn sàng khi hệ thống kết nối dịch vụ AI."><button type="button" disabled class="rounded-2xl bg-ai-start/30 px-5 font-bold text-white/70">Gửi</button></div></div>
        </div>
    </div>
</section>
@endsection
