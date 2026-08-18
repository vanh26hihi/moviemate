<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MovieMate - Đặt vé xem phim thông minh cùng AI')</title>
    <meta name="description" content="@yield('meta_description', 'MovieMate - Nền tảng đặt vé xem phim trực tuyến tích hợp AI thông minh.')">
    <x-brand.head-icons />
    @vite(['resources/css/app.css', 'resources/css/user.css', 'resources/js/app.js'])
    <script>
        (function() {
            var theme = localStorage.getItem('theme') || localStorage.getItem('moviemate_theme') || 'dark';
            document.documentElement.classList.toggle('light', theme === 'light');
        })();
    </script>
</head>
<body class="user-app app-page font-sans antialiased flex flex-col min-h-screen overflow-x-hidden @yield('body_class')">
    <a href="#main-content" class="sr-only focus:not-sr-only fixed left-4 top-4 z-[100] rounded-xl bg-brand-start px-4 py-3 font-bold text-white shadow-xl focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-brand-start">Bỏ qua điều hướng, đến nội dung chính</a>
    <header class="app-header fixed w-full top-0 z-50 backdrop-blur-xl border-b app-border transition-all duration-300">
        <div class="mx-auto max-w-[1680px] px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center gap-2 md:h-20 2xl:gap-4">
                <a href="{{ route('home') }}" class="flex shrink-0 items-center" aria-label="MovieMate - Trang chủ">
                    <x-brand.logo class="brand-logo--customer-header" />
                </a>

                <nav data-customer-desktop-nav aria-label="Điều hướng chính" class="hidden min-w-0 flex-1 items-center justify-between gap-1 rounded-full border app-border app-card p-1 2xl:flex">
                    <a href="{{ route('home') }}" @class(['user-nav-link', 'is-active' => request()->routeIs('home')]) @if(request()->routeIs('home')) aria-current="page" @endif>Trang chủ</a>
                    <a href="{{ route('user.movies.index') }}" @class(['user-nav-link', 'is-active' => request()->routeIs('user.movies.*')]) @if(request()->routeIs('user.movies.*')) aria-current="page" @endif>Phim</a>
                    <a href="{{ route('cinemas.index') }}" @class(['user-nav-link', 'is-active' => request()->routeIs('cinemas.*')]) @if(request()->routeIs('cinemas.*')) aria-current="page" @endif>Rạp</a>
                    <a href="{{ route('home') }}#home-showtime-calendar" class="user-nav-link">Lịch chiếu</a>
                    <a href="{{ route('foods.index') }}" @class(['user-nav-link', 'is-active' => request()->routeIs('foods.*')]) @if(request()->routeIs('foods.*')) aria-current="page" @endif>Đồ ăn</a>
                    <a href="{{ route('user.ai.recommend') }}" @class(['user-nav-link', 'is-active' => request()->routeIs('user.ai.*')]) @if(request()->routeIs('user.ai.*')) aria-current="page" @endif>
                        <i class="ph-fill ph-sparkle text-ai-start"></i> AI Gợi ý
                    </a>
                    <a href="{{ route('user.bookings.history') }}" @class(['user-nav-link', 'is-active' => request()->routeIs('user.bookings.history', 'user.bookings.ticket*')]) @if(request()->routeIs('user.bookings.history', 'user.bookings.ticket*')) aria-current="page" @endif>Đơn đặt vé của tôi</a>
                </nav>

                <details id="customer-cinema-selector" class="relative ml-auto hidden shrink-0 sm:block 2xl:ml-0">
                    <summary class="flex max-w-44 cursor-pointer list-none items-center gap-2 rounded-xl border app-border app-card px-3 py-2 text-sm font-bold app-text" aria-label="Chọn rạp ưu tiên"><i class="ph-fill ph-map-pin text-brand-start" aria-hidden="true"></i><span class="truncate">{{ $customerPreferredCinema?->name ?? 'Tất cả rạp' }}</span><i class="ph ph-caret-down text-xs" aria-hidden="true"></i></summary>
                    <div class="absolute right-0 top-full z-50 mt-2 w-72 rounded-2xl border app-border app-card p-2 shadow-2xl">
                        <form method="POST" action="{{ route('cinema-context.update') }}">@csrf<input type="hidden" name="cinema" value="all"><button type="submit" class="user-dropdown-link w-full text-left" @if(!$customerPreferredCinema) aria-current="true" @endif>Tất cả rạp</button></form>
                        @foreach($customerCinemas as $contextCinema)<form method="POST" action="{{ route('cinema-context.update') }}">@csrf<input type="hidden" name="cinema" value="{{ $contextCinema->code }}"><button type="submit" class="user-dropdown-link w-full text-left" @if($customerPreferredCinema?->is($contextCinema)) aria-current="true" @endif>{{ $contextCinema->name }}</button></form>@endforeach
                    </div>
                </details>

                <div class="hidden shrink-0 items-center gap-3 2xl:flex">
                    <button data-theme-toggle type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl app-card border app-border hover:border-brand-start transition-all text-sm app-muted hover:app-text"
                        title="Đổi giao diện sáng/tối" aria-label="Đổi giao diện sáng/tối" aria-pressed="false">
                        <span class="theme-icon flex items-center text-base"><i class="ph-fill ph-moon"></i></span>
                        <span class="theme-text hidden lg:inline">Tối</span>
                    </button>

                    @auth
                        <details class="user-account-menu relative">
                            <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border app-border app-card px-3 py-2 text-sm font-bold app-text">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-brand-start to-brand-end text-xs font-black text-white">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class="max-w-28 truncate">{{ auth()->user()->name }}</span>
                                <i class="ph ph-caret-down text-xs app-muted"></i>
                            </summary>
                            <div class="user-account-dropdown absolute right-0 top-full mt-2 w-56 rounded-2xl border app-border app-card p-2 shadow-2xl">
                                <a href="{{ route('user.profile') }}" class="user-dropdown-link"><i class="ph ph-user"></i> Hồ sơ</a>
                                <a href="{{ route('user.bookings.history') }}" class="user-dropdown-link"><i class="ph ph-ticket"></i> Lịch sử đặt vé</a>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="user-dropdown-link w-full text-left"><i class="ph ph-sign-out"></i> Đăng xuất</button>
                                </form>
                            </div>
                        </details>
                    @else
                        <a href="{{ route('login') }}" class="app-muted hover:app-text font-medium transition-colors text-sm">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="bg-gradient-to-r from-brand-start to-brand-end text-white px-5 py-2.5 rounded-full font-semibold text-sm hover:shadow-lg hover:shadow-brand-start/25 transition-all">
                            Đăng ký
                        </a>
                    @endauth
                </div>

                <div class="flex shrink-0 items-center gap-2 2xl:hidden">
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

        <div id="mobile-menu" data-customer-mobile-nav class="hidden border-b app-border app-secondary 2xl:hidden">
            <div class="mx-auto max-w-[1680px] space-y-1 px-4 pb-4 pt-2 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" @class(['user-mobile-link', 'is-active' => request()->routeIs('home')])>Trang chủ</a>
                <a href="{{ route('user.movies.index') }}" @class(['user-mobile-link', 'is-active' => request()->routeIs('user.movies.*')])>Phim</a>
                <a href="{{ route('cinemas.index') }}" @class(['user-mobile-link', 'is-active' => request()->routeIs('cinemas.*')])>Rạp</a>
                <a href="{{ route('home') }}#home-showtime-calendar" class="block px-3 py-2.5 rounded-lg text-sm font-medium app-muted hover:bg-brand-start/10 hover:text-brand-start transition-colors">Lịch chiếu</a>
                <a href="{{ route('foods.index') }}" @class(['user-mobile-link', 'is-active' => request()->routeIs('foods.*')])>Đồ ăn</a>
                <a href="{{ route('user.ai.recommend') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-ai-start hover:bg-ai-start/10 transition-colors">
                    <i class="ph-fill ph-sparkle"></i> AI Gợi ý
                </a>
                <a href="{{ route('user.bookings.history') }}" @class(['user-mobile-link', 'is-active' => request()->routeIs('user.bookings.history', 'user.bookings.ticket*')])>Đơn đặt vé của tôi</a>
                <div class="mt-3 border-t app-border pt-3 sm:hidden">
                    <p class="px-3 pb-2 text-xs font-bold uppercase tracking-wider app-muted">Rạp ưu tiên</p>
                    <form method="POST" action="{{ route('cinema-context.update') }}">@csrf<input type="hidden" name="cinema" value="all"><button type="submit" class="user-mobile-link w-full text-left" @if(!$customerPreferredCinema) aria-current="true" @endif><i class="ph ph-map-pin"></i>Tất cả rạp</button></form>
                    @foreach($customerCinemas as $contextCinema)<form method="POST" action="{{ route('cinema-context.update') }}">@csrf<input type="hidden" name="cinema" value="{{ $contextCinema->code }}"><button type="submit" class="user-mobile-link w-full text-left" @if($customerPreferredCinema?->is($contextCinema)) aria-current="true" @endif><i class="ph ph-buildings"></i>{{ $contextCinema->name }}</button></form>@endforeach
                </div>
                <div class="pt-3 mt-3 border-t app-border flex flex-col gap-2">
                    @auth
                        <a href="{{ route('user.profile') }}" class="user-mobile-link"><i class="ph ph-user mr-2"></i>Hồ sơ</a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="user-mobile-link w-full text-left"><i class="ph ph-sign-out mr-2"></i>Đăng xuất</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="block px-3 py-2.5 text-sm font-medium app-muted hover:app-text text-center border app-border rounded-lg">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="block px-3 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-brand-start to-brand-end text-center rounded-lg">Đăng ký</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <main id="main-content" class="flex-grow pt-16 md:pt-20 min-w-0" tabindex="-1">
        <x-flash-messages class="mx-auto max-w-7xl px-4 pt-5 sm:px-6 lg:px-8" :error-bag="$errors" :include-validation="! \Illuminate\Support\Facades\View::hasSection('suppress-global-validation-summary')" />
        @yield('content')
    </main>

    <footer class="app-secondary border-t app-border mt-16 pt-12 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-10">
                <div class="col-span-2 md:col-span-1">
                    <a href="{{ route('home') }}" class="mb-4 inline-flex items-center" aria-label="MovieMate - Trang chủ">
                        <x-brand.logo class="brand-logo--footer" />
                    </a>
                    <p class="app-muted text-sm leading-relaxed mb-5">
                        Nền tảng đặt vé xem phim tích hợp AI thông minh, mang đến trải nghiệm điện ảnh tiện lợi và cá nhân hóa.
                    </p>
                    <div class="flex gap-3"><x-brand.logo variant="mark" alt="" class="brand-logo--footer-mark" /><span class="app-muted text-sm self-center">Điện ảnh trong tầm tay</span></div>
                </div>

                <div>
                    <h3 class="app-text font-semibold mb-4 uppercase tracking-wider text-xs">Về MovieMate</h3>
                    <ul class="space-y-2.5"><li><a href="{{ route('user.movies.index') }}" class="app-muted hover:text-brand-start transition-colors text-sm">Danh sách phim</a></li><li><a href="{{ route('home') }}#home-showtime-calendar" class="app-muted hover:text-brand-start transition-colors text-sm">Lịch chiếu hôm nay</a></li><li><a href="{{ route('foods.index') }}" class="app-muted hover:text-brand-start transition-colors text-sm">Thực đơn tại rạp</a></li></ul>
                </div>

                <div>
                    <h3 class="app-text font-semibold mb-4 uppercase tracking-wider text-xs">Hỗ trợ</h3>
                    <ul class="space-y-2.5"><li><a href="{{ route('user.ai.recommend') }}" class="app-muted hover:text-ai-start transition-colors text-sm">AI gợi ý phim</a></li><li><a href="{{ route('user.bookings.history') }}" class="app-muted hover:text-brand-start transition-colors text-sm">Đơn đặt vé của tôi</a></li><li><a href="{{ route('user.profile') }}" class="app-muted hover:text-brand-start transition-colors text-sm">Tài khoản</a></li></ul>
                </div>

                <div>
                    <h3 class="app-text font-semibold mb-4 uppercase tracking-wider text-xs">Trải nghiệm</h3>
                    <p class="app-muted text-sm mb-4">Tìm phim, chọn suất chiếu, đặt ghế và nhận vé tại quầy trong một luồng thống nhất.</p>
                    <a href="{{ route('user.movies.index', ['status' => 'now_showing']) }}" class="btn-primary text-sm"><i class="ph-fill ph-ticket"></i>Đặt vé ngay</a>
                </div>
            </div>

            <div class="border-t app-border pt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="app-muted text-sm text-center sm:text-left safe-break">
                    <p>&copy; {{ date('Y') }} MovieMate. Tất cả quyền được bảo lưu. Dự án Tốt nghiệp.</p>
                    <p class="mt-1 text-xs">Dữ liệu phim và hình ảnh từ <a href="https://www.themoviedb.org/" target="_blank" rel="noopener noreferrer" class="font-bold text-brand-start hover:underline">TMDB</a>. MovieMate không được TMDB chứng thực hoặc chứng nhận.</p>
                </div>
                <div class="payment-badges flex flex-wrap items-center justify-center sm:justify-end gap-2">
                    <span class="payment-badge px-3 py-1 app-card border app-border rounded text-xs font-bold app-muted tracking-widest">VISA</span>
                    <span class="payment-badge px-3 py-1 app-card border app-border rounded text-xs font-bold app-muted tracking-widest">Mastercard</span>
                    <span class="payment-badge px-3 py-1 app-card border app-border rounded text-xs font-bold text-blue-400 tracking-widest">VNPay</span>
                </div>
            </div>
        </div>
    </footer>

    <a href="{{ route('user.ai.chatbot') }}" class="fixed bottom-6 right-6 w-12 h-12 md:w-14 md:h-14 bg-gradient-to-br from-ai-start to-ai-end rounded-full shadow-lg shadow-ai-start/30 flex items-center justify-center text-white hover:scale-110 transition-transform z-50" title="Trò chuyện với AI">
        <i class="ph-fill ph-robot text-2xl md:text-3xl"></i>
    </a>

    @stack('scripts')
</body>
</html>
