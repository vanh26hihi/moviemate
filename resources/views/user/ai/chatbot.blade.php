@extends('layouts.user')

@section('title', 'AI Chatbot - MovieMate')

@php
    $quickQuestions = [
        'Hôm nay có phim gì đang chiếu?',
        'Lịch chiếu tối nay như thế nào?',
        'Tôi muốn xem phim hành động',
        'Phim nào phù hợp xem với gia đình?',
        'Rạp MovieMate ở đâu?',
        'Làm sao để đặt vé?',
    ];

    $messages = $chatHistory->isNotEmpty()
        ? $chatHistory
        : collect($currentChat ? [(object) $currentChat] : []);
@endphp

@section('content')
<div class="chat-shell max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 h-[calc(100dvh-7rem)] md:h-[calc(100dvh-8rem)] min-h-[32rem]">
    <div class="app-card border app-border rounded-3xl h-full flex overflow-hidden shadow-2xl shadow-black/25">
        <aside class="w-72 border-r app-border flex-col hidden md:flex app-secondary">
            <div class="p-5 border-b app-border">
                <a href="{{ route('user.ai.recommend') }}" class="w-full py-2.5 bg-gradient-to-r from-ai-start to-ai-end text-white rounded-xl font-bold flex items-center justify-center gap-2 hover:shadow-lg hover:shadow-ai-start/20 transition-all text-sm">
                    <i class="ph-fill ph-magic-wand"></i> AI gợi ý phim
                </a>
            </div>

            <div class="p-5 flex-grow overflow-y-auto hide-scrollbar">
                <h3 class="text-[10px] font-bold app-muted uppercase tracking-wider mb-3">Câu hỏi nhanh</h3>
                <div class="space-y-1.5">
                    @foreach($quickQuestions as $question)
                        <button type="button" data-chat-question="{{ $question }}" class="quick-question w-full text-left p-3 rounded-xl app-card border app-border text-sm app-muted hover:app-text hover:border-ai-start/50 transition-colors">
                            {{ $question }}
                        </button>
                    @endforeach
                </div>

                <h3 class="text-[10px] font-bold app-muted uppercase tracking-wider mb-3 mt-6">Lịch sử chat</h3>
                <div class="space-y-1">
                    @forelse($chatHistory->reverse()->take(8) as $chat)
                        <div class="p-3 rounded-xl hover:app-card text-sm app-muted transition-colors line-clamp-2">
                            <i class="ph ph-chat-teardrop mr-2"></i>{{ $chat->message }}
                        </div>
                    @empty
                        <p class="text-sm app-muted leading-relaxed">
                            @auth
                                Chưa có lịch sử chat.
                            @else
                                Đăng nhập để lưu lịch sử chat.
                            @endauth
                        </p>
                    @endforelse
                </div>
            </div>
        </aside>

        <section class="flex-grow flex flex-col min-w-0">
            <div class="h-16 border-b app-border flex items-center justify-between px-5 app-card shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-ai-start to-ai-end flex items-center justify-center relative shrink-0">
                        <i class="ph-fill ph-robot text-white text-lg"></i>
                        <div class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-success rounded-full border-2 border-[var(--card-bg)]"></div>
                    </div>
                    <div>
                        <h1 class="font-bold app-text text-sm leading-tight">MovieMate AI</h1>
                        <p class="text-xs text-ai-start">Hỗ trợ phim, lịch chiếu, rạp và đặt vé</p>
                    </div>
                </div>
                @if($chatMeta)
                    <span class="hidden sm:inline-flex px-3 py-1 rounded-full border app-border app-muted text-xs">
                        Nguồn: {{ $chatMeta['source'] === 'fallback' ? 'Database fallback' : strtoupper($chatMeta['source']) }}
                    </span>
                @endif
            </div>

            @if($errors->any())
                <div class="mx-5 mt-4 rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @if($chatMeta && !empty($chatMeta['message']))
                <div class="mx-5 mt-4 rounded-2xl border border-warning/30 bg-warning/10 text-warning px-4 py-3 text-sm">
                    {{ $chatMeta['message'] }}
                </div>
            @endif

            <div class="flex-grow overflow-y-auto p-5 space-y-5 scroll-smooth" id="chat-messages">
                <div class="flex gap-3 max-w-[88%]">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-ai-start to-ai-end shrink-0 flex items-center justify-center mt-1">
                        <i class="ph-fill ph-robot text-white text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="app-secondary border app-border rounded-2xl rounded-tl-sm p-4 text-sm app-text leading-relaxed">
                            Xin chào! Tôi là trợ lý AI của MovieMate. Tôi có thể hỗ trợ bạn tìm phim, kiểm tra lịch chiếu, xem thông tin rạp hoặc hướng dẫn đặt vé.
                        </div>
                        <span class="text-[10px] app-muted mt-1.5 inline-block">MovieMate AI</span>
                    </div>
                </div>

                @foreach($messages as $chat)
                    <div class="flex gap-3 max-w-[88%] ml-auto justify-end">
                        <div class="text-right min-w-0">
                            <div class="bg-gradient-to-br from-brand-start to-brand-end rounded-2xl rounded-tr-sm p-4 text-sm text-white leading-relaxed inline-block text-left">
                                {{ $chat->message }}
                            </div>
                            <span class="text-[10px] app-muted mt-1.5 inline-block">
                                {{ optional($chat->created_at)->format('H:i d/m') ?? now()->format('H:i d/m') }}
                            </span>
                        </div>
                        <div class="w-8 h-8 rounded-full app-secondary shrink-0 overflow-hidden mt-1 border app-border flex items-center justify-center">
                            <i class="ph-fill ph-user text-sm app-muted"></i>
                        </div>
                    </div>

                    <div class="flex gap-3 max-w-[88%]">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-ai-start to-ai-end shrink-0 flex items-center justify-center mt-1">
                            <i class="ph-fill ph-robot text-white text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="app-secondary border app-border rounded-2xl rounded-tl-sm p-4 text-sm app-text leading-relaxed whitespace-pre-line">{{ $chat->response }}</div>
                            <span class="text-[10px] app-muted mt-1.5 inline-block">MovieMate AI</span>
                        </div>
                    </div>
                @endforeach

                @if($messages->isEmpty())
                    <div class="h-full min-h-40 flex items-center justify-center">
                        <div class="text-center max-w-md">
                            <div class="w-14 h-14 rounded-2xl bg-ai-start/10 text-ai-start flex items-center justify-center mx-auto mb-4">
                                <i class="ph-fill ph-chats-circle text-3xl"></i>
                            </div>
                            <h2 class="text-xl font-bold app-text mb-2">Bạn cần hỗ trợ gì?</h2>
                            <p class="app-muted text-sm leading-relaxed">Hỏi về phim đang chiếu, suất chiếu, địa chỉ rạp, giá vé hoặc quy trình đặt vé.</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="p-4 border-t app-border app-secondary shrink-0">
                <form action="{{ route('user.ai.chatbot.submit') }}" method="POST" class="flex items-end gap-2">
                    @csrf
                    <div class="relative flex-grow">
                        <textarea id="chat-input" name="message" rows="1" required
                            class="app-input w-full border app-border rounded-2xl py-3 pl-4 pr-4 text-sm focus:outline-none focus:border-ai-start transition-colors resize-none hide-scrollbar"
                            placeholder="Nhập câu hỏi về phim, lịch chiếu, rạp..."
                            style="min-height: 46px; max-height: 120px;">{{ old('message') }}</textarea>
                    </div>
                    <button type="submit" class="p-2.5 bg-ai-start hover:bg-ai-end text-white rounded-xl transition-colors shrink-0" title="Gửi">
                        <i class="ph-fill ph-paper-plane-right text-xl"></i>
                    </button>
                </form>
                <p class="text-[10px] text-center app-muted mt-2">AI có thể mắc lỗi. Vui lòng kiểm tra lại thông tin quan trọng trước khi đặt vé.</p>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const chatInput = document.getElementById('chat-input');
    const chatMessages = document.getElementById('chat-messages');

    document.querySelectorAll('.quick-question').forEach(function(button) {
        button.addEventListener('click', function() {
            if (!chatInput) return;
            chatInput.value = button.dataset.chatQuestion || '';
            chatInput.focus();
        });
    });

    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
</script>
@endpush
