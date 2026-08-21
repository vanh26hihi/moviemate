<div data-ai-assistant
    data-authenticated="{{ auth()->check() ? 'true' : 'false' }}"
    data-guest-stream-url="{{ route('user.ai.chatbot.stream') }}"
    data-guest-retry-url="{{ route('user.ai.chatbot.stream.retry') }}"
    data-conversations-url="{{ auth()->check() ? route('user.ai.conversations.index') : '' }}"
    data-fallback-url="{{ route('user.ai.chatbot') }}">
    <button type="button" data-ai-launcher class="ai-launcher" aria-haspopup="dialog" aria-controls="ai-assistant-panel" aria-expanded="false">
        <i class="ph-fill ph-robot" aria-hidden="true"></i><span class="sr-only">Mở trợ lý điện ảnh MovieMate</span>
    </button>

    <div data-ai-overlay class="ai-overlay" hidden></div>
    <section id="ai-assistant-panel" data-ai-panel class="ai-panel" role="dialog" aria-modal="true" aria-labelledby="ai-assistant-title" hidden>
        <header class="ai-panel-header">
            <div class="min-w-0">
                <p class="ai-eyebrow"><span aria-hidden="true"></span> Trợ lý điện ảnh</p>
                <h2 id="ai-assistant-title" data-ai-title>MovieMate AI</h2>
            </div>
            <div class="ai-panel-actions">
                @auth
                    <button type="button" data-ai-history-toggle class="ai-icon-button" aria-label="Lịch sử trò chuyện"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></button>
                    <button type="button" data-ai-new class="ai-icon-button" aria-label="Tạo cuộc trò chuyện mới"><i class="ph ph-plus" aria-hidden="true"></i></button>
                @endauth
                <a href="{{ route('user.ai.chatbot') }}" class="ai-icon-button" aria-label="Mở trang trò chuyện đầy đủ"><i class="ph ph-arrows-out" aria-hidden="true"></i></a>
                <button type="button" data-ai-close class="ai-icon-button" aria-label="Đóng trợ lý"><i class="ph ph-x" aria-hidden="true"></i></button>
            </div>
        </header>

        <div data-ai-history class="ai-history" hidden>
            <div class="ai-view-heading"><div><h3>Lịch sử</h3><p>Tiếp tục cuộc trò chuyện trước đây.</p></div><button type="button" data-ai-history-back class="ai-text-button">Quay lại</button></div>
            <div data-ai-history-list class="ai-history-list"></div>
            <button type="button" data-ai-history-more class="ai-secondary-button" hidden>Tải thêm</button>
        </div>

        <div data-ai-chat class="ai-chat-view">
            <div data-ai-messages class="ai-messages" role="log" aria-live="off" aria-relevant="additions text" tabindex="-1">
                <div data-ai-welcome class="ai-welcome">
                    <div class="ai-bot-mark"><i class="ph-fill ph-sparkle" aria-hidden="true"></i></div>
                    <h3>Hôm nay bạn muốn xem gì?</h3>
                    <p>Mình có thể tìm phim, suất chiếu, rạp và đồ ăn từ dữ liệu MovieMate.</p>
                    <div class="ai-prompts" aria-label="Câu hỏi gợi ý">
                        @foreach(['Phim nào đang chiếu?', 'Tối nay có suất nào?', 'Gợi ý phim cho tôi.', 'Có phim hành động nào?', 'MovieMate có đồ ăn gì?', 'Phim nào sắp chiếu?'] as $prompt)
                            <button type="button" data-ai-prompt="{{ $prompt }}">{{ $prompt }}</button>
                        @endforeach
                    </div>
                    @guest
                        <p class="ai-guest-note"><i class="ph ph-info" aria-hidden="true"></i><span><a href="{{ route('login') }}">Đăng nhập</a> để lưu và quản lý lịch sử trò chuyện.</span></p>
                    @endguest
                </div>
            </div>
            <p data-ai-status class="ai-status" role="status" aria-live="polite"></p>
            <form data-ai-form class="ai-composer">
                <label for="ai-assistant-message" class="sr-only">Nhập tin nhắn cho MovieMate AI</label>
                <textarea id="ai-assistant-message" data-ai-input rows="1" maxlength="1000" placeholder="Hỏi MovieMate về phim, suất chiếu…" required></textarea>
                <button type="submit" data-ai-send aria-label="Gửi tin nhắn"><i class="ph-fill ph-paper-plane-tilt" aria-hidden="true"></i></button>
            </form>
            <p class="ai-disclaimer">AI có thể nhầm. Hãy kiểm tra thông tin suất chiếu trước khi đặt vé.</p>
        </div>

        @auth
            <div data-ai-rename-dialog class="ai-nested-dialog" role="dialog" aria-modal="true" aria-labelledby="ai-rename-title" hidden>
                <form data-ai-rename-form class="ai-dialog-card"><h3 id="ai-rename-title">Đổi tên cuộc trò chuyện</h3><label for="ai-rename-input">Tên mới</label><input id="ai-rename-input" data-ai-rename-input maxlength="120" required><div><button type="button" data-ai-dialog-cancel class="ai-secondary-button">Hủy</button><button class="ai-primary-button">Lưu</button></div></form>
            </div>
            <div data-ai-delete-dialog class="ai-nested-dialog" role="dialog" aria-modal="true" aria-labelledby="ai-delete-title" hidden>
                <div class="ai-dialog-card"><h3 id="ai-delete-title">Xóa cuộc trò chuyện?</h3><p>Thao tác này không thể hoàn tác.</p><div><button type="button" data-ai-dialog-cancel class="ai-secondary-button">Hủy</button><button type="button" data-ai-delete-confirm class="ai-danger-button">Xóa</button></div></div>
            </div>
        @endauth
    </section>
</div>
