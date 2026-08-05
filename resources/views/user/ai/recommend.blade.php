@extends('layouts.user')

@section('title', 'AI gợi ý phim - MovieMate')

@section('content')
<section class="relative min-h-screen overflow-hidden py-14 md:py-20">
    <div class="absolute inset-0 bg-gradient-to-br from-dark-main via-dark-main to-[#111827]"></div>
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(124,58,237,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(37,99,235,0.22),transparent_30%),radial-gradient(circle_at_52%_100%,rgba(255,61,87,0.14),transparent_34%)] opacity-40"></div>
    <div class="relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl text-center">
            <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-ai-start/30 bg-ai-start/10 px-4 py-2"><i class="ph-fill ph-magic-wand text-ai-start"></i><span class="text-sm font-medium text-ai-start">Trí tuệ nhân tạo MovieMate</span></div>
            <h1 class="hero-title text-4xl font-extrabold app-text md:text-6xl">AI gợi ý phim <span class="bg-gradient-to-r from-ai-start to-ai-end bg-clip-text text-transparent">dành riêng cho bạn</span></h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed app-muted">Chức năng này sẽ sử dụng sở thích của bạn và lịch chiếu hiện có để đưa ra gợi ý phù hợp.</p>
        </div>

        <div class="mx-auto mt-10 grid max-w-4xl gap-6 lg:grid-cols-2">
            <div class="cinema-card rounded-3xl p-6 sm:p-8">
                <div class="mb-6 flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-ai-start/10 text-ai-start"><i class="ph-fill ph-sparkle text-xl"></i></span><div><h2 class="font-extrabold app-text">Yêu cầu của bạn</h2><p class="text-sm app-muted">Chọn sở thích để bắt đầu.</p></div></div>
                @if(\Illuminate\Support\Facades\Route::has('user.ai.recommend.submit'))
                    @include('components.home-ai-search')
                @else
                    <div class="space-y-4">
                        <label class="block text-sm font-bold app-text" for="ai-prompt">Bạn muốn xem gì hôm nay?</label>
                        <textarea id="ai-prompt" rows="4" disabled class="w-full resize-none rounded-2xl border app-border app-input px-4 py-3 opacity-70" placeholder="Tính năng gửi yêu cầu AI chưa được kết nối."></textarea>
                        <button type="button" disabled class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-2xl bg-ai-start/30 px-5 py-3 font-bold text-white/70"><i class="ph-fill ph-magic-wand"></i> Tạo gợi ý</button>
                    </div>
                @endif
            </div>

            <div class="cinema-card rounded-3xl p-6 text-center sm:p-8">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-ai-start/10 text-ai-start"><i class="ph-fill ph-robot text-3xl"></i></div>
                <h2 class="mt-5 text-2xl font-extrabold app-text">Chưa có gợi ý</h2>
                <p class="mx-auto mt-3 max-w-sm leading-relaxed app-muted">Gợi ý cá nhân hóa sẽ xuất hiện tại đây khi dịch vụ AI sẵn sàng.</p>
                <a href="{{ route('user.movies.index') }}" class="btn-secondary mt-6"><i class="ph-fill ph-film-strip"></i> Xem danh sách phim</a>
            </div>
        </div>
    </div>
</section>
@endsection
