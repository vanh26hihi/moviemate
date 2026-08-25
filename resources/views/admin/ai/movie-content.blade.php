@extends('layouts.admin')

@section('title', 'AI Movie Content Generator - MovieMate Admin')
@section('page-title', 'Tạo nội dung phim bằng AI')

@php
    $selectedTone = old('tone', $input['tone'] ?? 'attractive');
    $tones = [
        'attractive' => 'Hấp dẫn',
        'mysterious' => 'Bí ẩn',
        'professional' => 'Chuyên nghiệp',
        'funny' => 'Vui nhộn',
        'emotional' => 'Cảm xúc',
    ];

    $sections = [
        'short_description' => ['label' => 'Mô tả ngắn', 'hint' => 'Dùng cho đoạn giới thiệu nhanh trong danh sách phim.'],
        'seo_description' => ['label' => 'Mô tả SEO', 'hint' => 'Dùng cho nội dung chi tiết phim hoặc meta description mở rộng.'],
        'facebook_caption' => ['label' => 'Caption Facebook', 'hint' => 'Dùng cho bài đăng fanpage kèm lời kêu gọi đặt vé.'],
        'tiktok_caption' => ['label' => 'Caption TikTok', 'hint' => 'Dùng cho video ngắn hoặc bài đăng social.'],
    ];
@endphp

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-dark-card border border-dark-border rounded-2xl p-6 md:p-8 flex flex-col h-full relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-ai-start/10 rounded-full blur-[100px] pointer-events-none"></div>

            <div class="mb-6 relative z-10">
                <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-2">
                    <i class="ph-fill ph-magic-wand text-ai-start"></i> Thông tin đầu vào
                </h3>
                <p class="text-sm text-text-sub">Nhập dữ liệu cơ bản, AI sẽ tạo nội dung mô tả và caption để admin dùng lại khi tạo/sửa phim.</p>
            </div>

            @if($errors->any())
                <div class="mb-5 relative z-10 rounded-xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('admin.ai.movieContent.store') }}" method="POST" class="space-y-6 flex-grow relative z-10">
                @csrf

                <div>
                    <label for="title" class="block text-sm font-medium text-text-sub mb-2">Tên phim</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $input['title'] ?? '') }}" class="w-full px-4 py-3 bg-dark-main border border-dark-border rounded-xl text-white focus:outline-none focus:border-ai-start transition-colors" placeholder="Ví dụ: Godzilla x Kong: The New Empire" required>
                </div>

                <div>
                    <label for="genres" class="block text-sm font-medium text-text-sub mb-2">Thể loại</label>
                    <input id="genres" name="genres" type="text" value="{{ old('genres', $input['genres'] ?? '') }}" class="w-full px-4 py-3 bg-dark-main border border-dark-border rounded-xl text-white focus:outline-none focus:border-ai-start transition-colors" placeholder="Ví dụ: Hành động, viễn tưởng, phiêu lưu">
                </div>

                <div>
                    <label for="original_description" class="block text-sm font-medium text-text-sub mb-2">Mô tả gốc</label>
                    <textarea id="original_description" name="original_description" rows="6" class="w-full px-4 py-3 bg-dark-main border border-dark-border rounded-xl text-white focus:outline-none focus:border-ai-start transition-colors resize-y" placeholder="Nhập synopsis, nội dung phim hoặc ghi chú marketing...">{{ old('original_description', $input['original_description'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-sub mb-2">Tone nội dung</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($tones as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="tone" value="{{ $value }}" class="peer sr-only" @checked($selectedTone === $value)>
                                <span class="min-h-10 flex items-center justify-center text-center px-2 py-2 bg-dark-main border border-dark-border rounded-lg text-sm text-text-sub peer-checked:bg-ai-start/10 peer-checked:border-ai-start peer-checked:text-ai-start font-medium transition-colors">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full py-4 bg-gradient-to-r from-ai-start to-ai-end text-white rounded-xl font-bold text-lg hover:shadow-lg hover:shadow-ai-start/30 transition-transform transform hover:-translate-y-0.5 flex items-center justify-center gap-2 group">
                        <i class="ph-bold ph-sparkle group-hover:animate-spin"></i> Tạo nội dung
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-dark-main border border-dark-border rounded-2xl flex flex-col h-full relative">
            <div class="p-4 border-b border-dark-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-dark-card/50 rounded-t-2xl">
                <div>
                    <h3 class="font-bold text-white text-sm">Kết quả tạo nội dung</h3>
                    @if($meta)
                        <p class="text-xs text-text-sub mt-1">Nguồn: {{ $meta['source'] === 'fallback' ? 'Fallback mẫu' : strtoupper($meta['source']) }}</p>
                    @endif
                </div>
                <button type="button" data-copy-all class="px-3 py-2 bg-dark-main border border-dark-border text-text-sub hover:text-white hover:border-ai-start rounded-lg transition-colors text-xs font-bold flex items-center justify-center gap-2">
                    <i class="ph ph-copy"></i> Copy tất cả
                </button>
            </div>

            @if($meta && !empty($meta['message']))
                <div class="mx-6 mt-5 rounded-xl border border-warning/30 bg-warning/10 text-warning px-4 py-3 text-sm">
                    {{ $meta['message'] }}
                </div>
            @endif

            <div class="p-6 flex-grow overflow-y-auto hide-scrollbar space-y-6">
                @if($result)
                    @foreach($sections as $key => $section)
                        <div class="content-section">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <div>
                                    <h4 class="text-sm font-medium text-text-sub uppercase tracking-wider">{{ $section['label'] }}</h4>
                                    <p class="text-xs text-text-sub/80 mt-1">{{ $section['hint'] }}</p>
                                </div>
                                <button type="button" data-copy-target="{{ $key }}" class="shrink-0 w-9 h-9 rounded-lg bg-dark-card border border-dark-border text-text-sub hover:text-white hover:border-ai-start transition-colors flex items-center justify-center" title="Sao chép">
                                    <i class="ph ph-copy"></i>
                                </button>
                            </div>
                            <div id="{{ $key }}" class="bg-dark-card border border-dark-border p-4 rounded-xl text-sm text-white leading-relaxed whitespace-pre-line">{{ $result[$key] ?? '' }}</div>
                        </div>
                    @endforeach
                @else
                    <div class="h-full min-h-[28rem] flex items-center justify-center">
                        <div class="text-center max-w-md">
                            <div class="w-16 h-16 rounded-2xl bg-ai-start/10 text-ai-start flex items-center justify-center mx-auto mb-4">
                                <i class="ph-fill ph-magic-wand text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">Chưa có nội dung</h3>
                            <p class="text-sm text-text-sub leading-relaxed">Nhập tên phim, thể loại, mô tả gốc và tone ở bên trái để tạo mô tả ngắn, mô tả SEO và caption social.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function copyText(text, button) {
        if (!text) return;

        navigator.clipboard.writeText(text).then(function() {
            const original = button.innerHTML;
            button.innerHTML = '<i class="ph ph-check"></i>';
            setTimeout(function() {
                button.innerHTML = original;
            }, 1200);
        });
    }

    document.querySelectorAll('[data-copy-target]').forEach(function(button) {
        button.addEventListener('click', function() {
            const target = document.getElementById(button.dataset.copyTarget);
            copyText(target ? target.innerText.trim() : '', button);
        });
    });

    const copyAllButton = document.querySelector('[data-copy-all]');
    if (copyAllButton) {
        copyAllButton.addEventListener('click', function() {
            const text = Array.from(document.querySelectorAll('.content-section')).map(function(section) {
                const title = section.querySelector('h4')?.innerText || '';
                const content = section.querySelector('[id]')?.innerText || '';
                return title + ':\n' + content.trim();
            }).filter(Boolean).join('\n\n');

            copyText(text, copyAllButton);
        });
    }
</script>
@endpush
