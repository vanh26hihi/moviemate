<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MovieMate - Đặt vé xem phim thông minh cùng AI')</title>
    <meta name="description" content="@yield('meta_description', 'MovieMate - Nền tảng đặt vé xem phim trực tuyến tích hợp AI thông minh.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <script>
        (function() {
            var theme = localStorage.getItem('theme') || localStorage.getItem('moviemate_theme') || 'dark';
            document.documentElement.classList.toggle('light', theme === 'light');
        })();
    </script>
</head>
<body class="app-page font-sans antialiased flex flex-col min-h-screen overflow-x-hidden">
    <header class="app-header fixed w-full top-0 z-50 backdrop-blur-xl border-b app-border transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0">
                    <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-brand-start to-brand-end text-white flex items-center justify-center shadow-lg shadow-brand-start/25">
                        <i class="ph-fill ph-film-strip text-2xl"></i>
                    </span>
                    <span class="text-xl md:text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-start to-brand-end">
                        MovieMate
                    </span>
                </a>

                <nav class="hidden md:flex items-center gap-1 rounded-full app-card border app-border p-1">
                    <a href="{{ route('home') }}" class="px-4 py-2 rounded-full app-text hover:text-brand-start hover:bg-white/5 transition-colors font-medium text-sm">Trang chủ</a>
                    <a href="{{ route('user.movies.index') }}" class="px-4 py-2 rounded-full app-muted hover:text-brand-start hover:bg-white/5 transition-colors font-medium text-sm">Phim</a>
                    <a href="{{ route('user.showtimes.index') }}" class="px-4 py-2 rounded-full app-muted hover:text-brand-start hover:bg-white/5 transition-colors font-medium text-sm">Lịch chiếu</a>
                    <a href="{{ route('foods.index') }}" class="px-4 py-2 rounded-full app-muted hover:text-brand-start hover:bg-white/5 transition-colors font-medium text-sm">Đồ ăn</a>
                    <a href="{{ route('user.ai.recommend') }}" class="px-4 py-2 rounded-full flex items-center gap-1.5 app-muted hover:text-ai-start hover:bg-ai-start/10 transition-colors font-medium text-sm">
                        <i class="ph-fill ph-sparkle text-ai-start"></i> AI Gợi ý
                    </a>
                    <a href="{{ route('user.bookings.history') }}" class="px-4 py-2 rounded-full app-muted hover:text-brand-start hover:bg-white/5 transition-colors font-medium text-sm">Vé của tôi</a>
                </nav>

                <div class="hidden md:flex items-center gap-3">
                    <button data-theme-toggle type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl app-card border app-border hover:border-brand-start transition-all text-sm app-muted hover:app-text"
                        title="Đổi giao diện sáng/tối" aria-label="Đổi giao diện sáng/tối" aria-pressed="false">
                        <span class="theme-icon flex items-center text-base"><i class="ph-fill ph-moon"></i></span>
                        <span class="theme-text hidden lg:inline">Tối</span>
                    </button>

                    @auth
                        <a href="{{ route('user.bookings.history') }}" class="app-muted hover:app-text font-medium transition-colors text-sm">Tài khoản</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="bg-gradient-to-r from-brand-start to-brand-end text-white px-5 py-2.5 rounded-full font-semibold text-sm hover:shadow-lg hover:shadow-brand-start/25 transition-all">
                                Đăng xuất
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="app-muted hover:app-text font-medium transition-colors text-sm">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="bg-gradient-to-r from-brand-start to-brand-end text-white px-5 py-2.5 rounded-full font-semibold text-sm hover:shadow-lg hover:shadow-brand-start/25 transition-all">
                            Đăng ký
                        </a>
                    @endauth
                </div>

                <div class="md:hidden flex items-center gap-2">
                    <button data-theme-toggle type="button"
                        class="inline-flex items-center gap-1 px-2.5 py-2 rounded-lg app-card border app-border text-sm app-muted"
                        aria-label="Đổi giao diện sáng/tối" aria-pressed="false">
                        <span class="theme-icon flex items-center text-base"><i class="ph-fill ph-moon"></i></span>
                    </button>
                    <button id="mobile-menu-btn" class="app-muted hover:app-text focus:outline-none p-2" aria-label="Mở menu" aria-expanded="false" aria-controls="mobile-menu">
                        <i class="ph ph-list text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden app-secondary border-b app-border">
            <div class="px-4 pt-2 pb-4 space-y-1">
                <a href="{{ route('home') }}" class="block px-3 py-2.5 rounded-lg text-sm font-medium app-text hover:bg-brand-start/10 hover:text-brand-start transition-colors">Trang chủ</a>
                <a href="{{ route('user.movies.index') }}" class="block px-3 py-2.5 rounded-lg text-sm font-medium app-muted hover:bg-brand-start/10 hover:text-brand-start transition-colors">Phim</a>
                <a href="{{ route('user.showtimes.index') }}" class="block px-3 py-2.5 rounded-lg text-sm font-medium app-muted hover:bg-brand-start/10 hover:text-brand-start transition-colors">Lịch chiếu</a>
                <a href="{{ route('foods.index') }}" class="block px-3 py-2.5 rounded-lg text-sm font-medium app-muted hover:bg-brand-start/10 hover:text-brand-start transition-colors">Đồ ăn</a>
                <a href="{{ route('user.ai.recommend') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-ai-start hover:bg-ai-start/10 transition-colors">
                    <i class="ph-fill ph-sparkle"></i> AI Gợi ý
                </a>
                <a href="{{ route('user.bookings.history') }}" class="block px-3 py-2.5 rounded-lg text-sm font-medium app-muted hover:bg-brand-start/10 hover:text-brand-start transition-colors">Vé của tôi</a>
                <div class="pt-3 mt-3 border-t app-border flex flex-col gap-2">
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full block px-3 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-brand-start to-brand-end text-center rounded-lg">Đăng xuất</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="block px-3 py-2.5 text-sm font-medium app-muted hover:app-text text-center border app-border rounded-lg">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="block px-3 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-brand-start to-brand-end text-center rounded-lg">Đăng ký</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <main class="flex-grow pt-16 md:pt-20 min-w-0">
        @if(session('success') || session('error'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-5">
                @if(session('success'))
                    <div class="rounded-2xl border border-success/30 bg-success/10 text-success px-4 py-3 text-sm font-medium">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm font-medium">{{ session('error') }}</div>
                @endif
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="app-secondary border-t app-border mt-16 pt-12 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-10">
                <div class="col-span-2 md:col-span-1">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5 mb-4">
                        <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-brand-start to-brand-end text-white flex items-center justify-center">
                            <i class="ph-fill ph-film-strip text-2xl"></i>
                        </span>
                        <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-start to-brand-end">MovieMate</span>
                    </a>
                    <p class="app-muted text-sm leading-relaxed mb-5">
                        Nền tảng đặt vé xem phim tích hợp AI thông minh, mang đến trải nghiệm điện ảnh tiện lợi và cá nhân hóa.
                    </p>
                    <div class="flex gap-3">
                        <a href="#" class="w-9 h-9 rounded-full app-card border app-border flex items-center justify-center app-muted hover:text-brand-start hover:border-brand-start transition-all"><i class="ph-fill ph-facebook-logo text-lg"></i></a>
                        <a href="#" class="w-9 h-9 rounded-full app-card border app-border flex items-center justify-center app-muted hover:text-brand-start hover:border-brand-start transition-all"><i class="ph-fill ph-instagram-logo text-lg"></i></a>
                        <a href="#" class="w-9 h-9 rounded-full app-card border app-border flex items-center justify-center app-muted hover:text-brand-start hover:border-brand-start transition-all"><i class="ph-fill ph-youtube-logo text-lg"></i></a>
                    </div>
                </div>

                <div>
                    <h3 class="app-text font-semibold mb-4 uppercase tracking-wider text-xs">Về MovieMate</h3>
                    <ul class="space-y-2.5">
                        <li><a href="#" class="app-muted hover:text-brand-start transition-colors text-sm">Giới thiệu</a></li>
                        <li><a href="#" class="app-muted hover:text-brand-start transition-colors text-sm">Hệ thống rạp chiếu</a></li>
                        <li><a href="#" class="app-muted hover:text-brand-start transition-colors text-sm">Tuyển dụng</a></li>
                        <li><a href="#" class="app-muted hover:text-brand-start transition-colors text-sm">Liên hệ</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="app-text font-semibold mb-4 uppercase tracking-wider text-xs">Hỗ trợ</h3>
                    <ul class="space-y-2.5">
                        <li><a href="#" class="app-muted hover:text-brand-start transition-colors text-sm">Câu hỏi thường gặp</a></li>
                        <li><a href="#" class="app-muted hover:text-brand-start transition-colors text-sm">Chính sách bảo mật</a></li>
                        <li><a href="#" class="app-muted hover:text-brand-start transition-colors text-sm">Điều khoản sử dụng</a></li>
                        <li><a href="#" class="app-muted hover:text-brand-start transition-colors text-sm">Quy định đổi trả</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="app-text font-semibold mb-4 uppercase tracking-wider text-xs">Tải ứng dụng</h3>
                    <p class="app-muted text-sm mb-4">Trải nghiệm đặt vé mượt mà trên ứng dụng MovieMate.</p>
                    <div class="space-y-2.5">
                        <a href="#" class="flex items-center gap-3 app-card border app-border rounded-xl px-4 py-2.5 hover:border-brand-start transition-colors">
                            <i class="ph-fill ph-apple-logo text-2xl app-text"></i>
                            <div><p class="text-xs app-muted">Download on the</p><p class="text-sm font-semibold app-text">App Store</p></div>
                        </a>
                        <a href="#" class="flex items-center gap-3 app-card border app-border rounded-xl px-4 py-2.5 hover:border-brand-start transition-colors">
                            <i class="ph-fill ph-google-play-logo text-2xl text-brand-start"></i>
                            <div><p class="text-xs app-muted">GET IT ON</p><p class="text-sm font-semibold app-text">Google Play</p></div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-t app-border pt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                <p class="app-muted text-sm text-center sm:text-left safe-break">
                    &copy; {{ date('Y') }} MovieMate. Tất cả quyền được bảo lưu. Dự án Tốt nghiệp.
                </p>
                <div class="payment-badges flex flex-wrap items-center justify-center sm:justify-end gap-2">
                    <span class="payment-badge px-3 py-1 app-card border app-border rounded text-xs font-bold app-muted tracking-widest">VISA</span>
                    <span class="payment-badge px-3 py-1 app-card border app-border rounded text-xs font-bold app-muted tracking-widest">Mastercard</span>
                    <span class="payment-badge px-3 py-1 app-card border app-border rounded text-xs font-bold text-blue-400 tracking-widest">VNPay</span>
                </div>
            </div>
        </div>
    </footer>

    <a href="{{ route('user.ai.chatbot') }}" class="fixed bottom-6 right-6 w-12 h-12 md:w-14 md:h-14 bg-gradient-to-br from-ai-start to-ai-end rounded-full shadow-lg shadow-ai-start/30 flex items-center justify-center text-white hover:scale-110 transition-transform z-50" title="Chat với AI">
        <i class="ph-fill ph-robot text-2xl md:text-3xl"></i>
    </a>

    <script>
        const mobileMenuButton = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                const isHidden = mobileMenu.classList.toggle('hidden');
                mobileMenuButton.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            });
        }
    </script>

    @stack('scripts')
</body>
</html>
