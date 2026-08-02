<form action="{{ route('user.ai.recommend.submit') }}" method="POST" class="mt-10 grid grid-cols-1 gap-3 cinema-card p-3 sm:p-4 lg:grid-cols-[1fr_auto]">
    @csrf
    <input type="hidden" name="mood" value="chill">
    <input type="hidden" name="preferred_time" value="tonight">
    <input type="hidden" name="companion" value="friends">
    <label class="flex items-center gap-3 rounded-2xl border app-border app-input px-3 py-2 sm:px-4">
        <i class="ph-fill ph-sparkle text-xl text-ai-start"></i>
        <span class="hidden whitespace-nowrap font-bold app-text md:inline">Bạn muốn xem phim gì hôm nay?</span>
        <input name="location" type="text" class="w-full bg-transparent py-2 app-text placeholder:text-text-sub/70 focus:outline-none" placeholder="Ví dụ: Tôi thích phim hành động, muốn xem tối nay ở Hà Nội...">
    </label>
    <button class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-ai-start to-ai-end px-6 py-3 font-extrabold text-white">
        <i class="ph-fill ph-magic-wand"></i>
        Gợi ý bằng AI
    </button>
</form>
