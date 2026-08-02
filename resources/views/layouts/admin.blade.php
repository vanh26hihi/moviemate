<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MovieMate Admin Panel')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        (function () {
            var theme = localStorage.getItem('theme') || localStorage.getItem('moviemate_theme') || 'dark';
            document.documentElement.classList.toggle('light', theme === 'light');
        })();
    </script>
</head>
<body class="app-page font-sans antialiased flex h-screen overflow-hidden">
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden hidden"></div>

    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-64 app-sidebar border-r app-border transform -translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col h-full">
        <div class="h-16 lg:h-20 flex items-center px-6 border-b app-border shrink-0">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <i class="ph-fill ph-film-strip text-3xl text-brand-start"></i>
                <div class="leading-tight">
                    <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-start to-brand-end">MovieMate</span>
                    <span class="block text-[10px] uppercase tracking-widest app-muted font-bold">Admin Panel</span>
                </div>
            </a>
        </div>

        <nav class="flex-grow py-4 px-4 space-y-0.5 overflow-y-auto hide-scrollbar">
            <p class="px-3 text-[10px] font-bold app-muted uppercase tracking-wider mb-1 mt-4 first:mt-0">Tổng quan</p>
            <x-admin.nav-link route-name="admin.dashboard" active-pattern="admin.dashboard" label="Dashboard" icon="ph-squares-four" />

            <p class="px-3 text-[10px] font-bold app-muted uppercase tracking-wider mb-1 mt-5">Quản lý rạp &amp; phim</p>
            <x-admin.nav-link route-name="admin.movies.index" active-pattern="admin.movies.*" label="Phim" icon="ph-film-slate" />
            <x-admin.nav-link route-name="admin.genres.index" active-pattern="admin.genres.*" label="Thể loại" icon="ph-tag" />
            <x-admin.nav-link route-name="admin.cinemas.index" active-pattern="admin.cinemas.*" label="Rạp chiếu" icon="ph-buildings" />
            <x-admin.nav-link route-name="admin.rooms.index" active-pattern="admin.rooms.*" label="Phòng chiếu" icon="ph-projector-screen" />
            <x-admin.nav-link route-name="admin.seats.index" active-pattern="admin.seats.*" label="Ghế" icon="ph-armchair" />
            <x-admin.nav-link route-name="admin.showtimes.index" active-pattern="admin.showtimes.*" label="Suất chiếu" icon="ph-calendar-plus" />

            <p class="px-3 text-[10px] font-bold app-muted uppercase tracking-wider mb-1 mt-5">Kinh doanh</p>
            <x-admin.nav-link route-name="admin.foods.index" active-pattern="admin.foods.*" label="Món ăn" icon="ph-burger" />
            <x-admin.nav-link route-name="admin.food-orders.index" active-pattern="admin.food-orders.*" label="Đơn đồ ăn" icon="ph-shopping-bag" />
            <x-admin.nav-link route-name="admin.bookings.index" active-pattern="admin.bookings.*" label="Vé đặt" icon="ph-ticket" />
            <x-admin.nav-link route-name="admin.vouchers.index" active-pattern="admin.vouchers.*" label="Voucher" icon="ph-ticket" />
            <x-admin.nav-link route-name="admin.users.index" active-pattern="admin.users.*" label="Người dùng" icon="ph-users" />
            <x-admin.nav-link route-name="admin.reviews.index" active-pattern="admin.reviews.*" label="Đánh giá" icon="ph-star" />

            <p class="px-3 text-[10px] font-bold app-muted uppercase tracking-wider mb-1 mt-5">Báo cáo &amp; AI</p>
            <x-admin.nav-link route-name="admin.analytics.revenue" active-pattern="admin.analytics.revenue" label="Doanh thu" icon="ph-chart-line-up" />
            <x-admin.nav-link route-name="admin.analytics.topMovies" active-pattern="admin.analytics.topMovies" label="Phim bán chạy" icon="ph-crown" />
            <x-admin.nav-link route-name="admin.ai.movieContent" active-pattern="admin.ai.*" label="AI Tools" icon="ph-magic-wand" />
        </nav>

        <div class="p-4 border-t app-border shrink-0">
            <a href="{{ route('home') }}" target="_blank" class="flex items-center justify-center gap-2 w-full py-2.5 app-card border app-border app-muted rounded-xl hover:text-brand-start hover:border-brand-start transition-colors text-sm font-medium"><i class="ph ph-arrow-square-out text-lg"></i> Về website</a>
        </div>
    </aside>

    <main class="admin-shell flex-grow flex flex-col min-w-0 app-bg relative h-full overflow-hidden">
        <header class="h-16 lg:h-20 flex items-center justify-between px-4 sm:px-8 border-b app-border app-card backdrop-blur-md sticky top-0 z-30 shrink-0">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-btn" type="button" class="lg:hidden app-muted hover:app-text" aria-label="Mở menu" aria-expanded="false" aria-controls="sidebar"><i class="ph ph-list text-2xl"></i></button>
                <h1 class="text-lg font-bold app-text hidden sm:block">@yield('page-title')</h1>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <div class="relative hidden md:block w-56">
                    <i class="ph ph-magnifying-glass app-muted text-sm absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"></i>
                    <input type="text" class="app-input w-full pl-9 pr-3 py-2 rounded-lg border app-border focus:outline-none focus:border-brand-start transition-colors text-sm" placeholder="Tìm kiếm (Ctrl+K)">
                </div>
                <button type="button" class="relative app-muted hover:app-text transition-colors p-2" aria-label="Thông báo"><i class="ph ph-bell text-lg"></i><span class="absolute top-1 right-1 w-2 h-2 bg-brand-start rounded-full"></span></button>
                <button data-theme-toggle type="button" class="flex items-center gap-1.5 px-3 py-2 rounded-xl app-card border app-border app-muted hover:border-brand-start transition-all text-sm" aria-label="Đổi giao diện sáng/tối" aria-pressed="false"><span class="theme-icon flex items-center text-base"><i class="ph-fill ph-moon"></i></span><span class="theme-text hidden lg:inline text-xs font-medium">Tối</span></button>
                <div class="flex items-center gap-3 pl-3 border-l app-border">
                    <div class="hidden sm:block text-right"><p class="text-sm font-bold app-text leading-tight">Admin MovieMate</p><p class="text-[10px] uppercase tracking-wider text-brand-start font-bold">Quản trị viên</p></div>
                    <span class="w-9 h-9 rounded-full app-bg border app-border flex items-center justify-center text-brand-start"><i class="ph-fill ph-user text-lg"></i></span>
                </div>
            </div>
        </header>
        <div class="flex-grow overflow-y-auto"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8 pb-12"><div class="sm:hidden mb-4"><h1 class="text-xl font-bold app-heading">@yield('page-title')</h1></div>@yield('content')</div></div>
    </main>

    <script>
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const toggleBtn = document.getElementById('mobile-menu-btn');
        function toggleSidebar() {
            const isHidden = sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
            toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        }
        if (toggleBtn && sidebar && backdrop) { toggleBtn.addEventListener('click', toggleSidebar); backdrop.addEventListener('click', toggleSidebar); }
    </script>
    @stack('scripts')
</body>
</html>
