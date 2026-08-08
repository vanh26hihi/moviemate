<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Khu vực quản trị MovieMate')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            var theme = localStorage.getItem('theme') || localStorage.getItem('moviemate_theme') || 'dark';
            document.documentElement.classList.toggle('light', theme === 'light');
        })();
    </script>
</head>
<body class="app-page font-sans antialiased flex h-screen overflow-hidden">
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden hidden"></div>

    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-64 app-sidebar border-r app-border transform -translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col h-full overflow-hidden">
        <div class="h-16 lg:h-20 flex items-center px-6 border-b app-border shrink-0" data-admin-sidebar-logo>
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <i class="ph-fill ph-film-strip text-3xl text-brand-start"></i>
                <div class="leading-tight">
                    <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-start to-brand-end">MovieMate</span>
                    <span class="block text-[10px] uppercase tracking-widest app-muted font-bold">Khu vực quản trị</span>
                </div>
            </a>
        </div>

        @php
            $adminNavigation = [
                ['label' => 'Tổng quan', 'items' => [
                    ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'permission' => 'dashboard.view', 'label' => 'Tổng quan', 'icon' => 'ph-squares-four'],
                    ['route' => 'admin.reports.index', 'active' => 'admin.reports.*', 'permission' => 'reports.view', 'label' => 'Báo cáo', 'icon' => 'ph-chart-line-up'],
                ]],
                ['label' => 'Nội dung', 'items' => [
                    ['route' => 'admin.movies.index', 'active' => 'admin.movies.*', 'permission' => 'movies.view', 'label' => 'Phim', 'icon' => 'ph-film-slate'],
                    ['route' => 'admin.genres.index', 'active' => 'admin.genres.*', 'permission' => 'genres.view', 'label' => 'Thể loại', 'icon' => 'ph-tag'],
                    ['route' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'permission' => 'reviews.view', 'label' => 'Đánh giá phim', 'icon' => 'ph-star'],
                    ['route' => 'admin.loyalty-settings.edit', 'active' => 'admin.loyalty-settings.*', 'permission' => 'reviews.view', 'label' => 'Cấu hình điểm', 'icon' => 'ph-coins'],
                    ['route' => 'admin.loyalty.index', 'active' => 'admin.loyalty.index', 'permission' => 'activity_logs.view', 'label' => 'Nhật ký điểm', 'icon' => 'ph-list-checks'],
                ]],
                ['label' => 'Rạp & lịch chiếu', 'items' => [
                    ['route' => 'admin.cinemas.index', 'active' => ['admin.cinema.*', 'admin.cinemas.*'], 'permission' => 'cinemas.view', 'label' => 'Chi nhánh', 'icon' => 'ph-buildings'],
                    ['route' => 'admin.rooms.index', 'active' => ['admin.rooms.*', 'admin.seats.*'], 'permission' => 'rooms.view', 'label' => 'Phòng chiếu', 'icon' => 'ph-projector-screen'],
                    ['route' => 'admin.layout-templates.index', 'active' => 'admin.layout-templates.*', 'permission' => 'layout_templates.view', 'label' => 'Mẫu sơ đồ phòng', 'icon' => 'ph-grid-four'],
                    ['route' => 'admin.showtimes.index', 'active' => 'admin.showtimes.*', 'permission' => 'showtimes.view', 'label' => 'Suất chiếu', 'icon' => 'ph-calendar-plus'],
                    ['route' => 'admin.pricing-rules.index', 'active' => 'admin.pricing-rules.*', 'permission' => 'pricing.view', 'label' => 'Bảng giá vé', 'icon' => 'ph-ticket'],
                ]],
                ['label' => 'Kinh doanh', 'items' => [
                    ['route' => 'admin.bookings.index', 'active' => 'admin.bookings.*', 'permission' => 'bookings.view', 'label' => 'Đơn đặt vé', 'icon' => 'ph-ticket'],
                    ['route' => 'admin.payments.index', 'active' => 'admin.payments.*', 'permission' => 'payments.view', 'label' => 'Thanh toán', 'icon' => 'ph-credit-card'],
                    ['route' => 'admin.discounts.index', 'active' => 'admin.discounts.*', 'permission' => 'discounts.view', 'label' => 'Mã giảm giá', 'icon' => 'ph-ticket-percent'],
                    ['route' => 'admin.ticket-checkins.index', 'active' => 'admin.ticket-checkins.*', 'permission' => 'ticket_checkins.view', 'label' => 'Lịch sử soát vé', 'icon' => 'ph-qr-code'],
                ]],
                ['label' => 'Dịch vụ', 'items' => [
                    ['route' => 'admin.foods.index', 'active' => 'admin.foods.*', 'permission' => 'foods.view', 'label' => 'Món ăn', 'icon' => 'ph-burger'],
                    ['route' => 'admin.food-orders.index', 'active' => 'admin.food-orders.*', 'permission' => 'food-orders.view', 'label' => 'Đơn đồ ăn', 'icon' => 'ph-shopping-bag'],
                ]],
                ['label' => 'Hệ thống', 'items' => [
                    ['route' => 'admin.users.index', 'active' => 'admin.users.*', 'permission' => 'users.view', 'label' => 'Người dùng', 'icon' => 'ph-users'],
                    ['route' => 'admin.roles.index', 'active' => 'admin.roles.*', 'permission' => 'roles.view', 'label' => 'Vai trò và quyền', 'icon' => 'ph-shield-check'],
                    ['route' => 'admin.activity-logs.index', 'active' => 'admin.activity-logs.*', 'permission' => 'activity_logs.view', 'label' => 'Nhật ký hoạt động', 'icon' => 'ph-list-magnifying-glass'],
                ]],
            ];
        @endphp

        <nav class="admin-sidebar-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4" data-admin-sidebar-scroll aria-label="Điều hướng quản trị">
            @foreach($adminNavigation as $group)
                @php
                    $visibleItems = collect($group['items'])->filter(fn (array $item): bool =>
                        \Illuminate\Support\Facades\Route::has($item['route'])
                        && \Illuminate\Support\Facades\Gate::allows($item['permission'])
                    );
                @endphp
                @if($visibleItems->isNotEmpty())
                    <section class="mt-5 first:mt-0" aria-labelledby="admin-nav-group-{{ $loop->index }}">
                        <h2 id="admin-nav-group-{{ $loop->index }}" class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider app-muted">{{ $group['label'] }}</h2>
                        <div class="space-y-0.5">
                            @foreach($visibleItems as $item)
                                <x-admin.nav-link :route-name="$item['route']" :active-pattern="$item['active']" :label="$item['label']" :icon="$item['icon']" :badge="$item['badge'] ?? null" />
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </nav>

        <div class="p-4 border-t app-border shrink-0" data-admin-sidebar-footer>
            <a href="{{ route('home') }}" target="_blank" class="flex items-center justify-center gap-2 w-full py-2.5 app-card border app-border app-muted rounded-xl hover:text-brand-start hover:border-brand-start transition-colors text-sm font-medium"><i class="ph ph-arrow-square-out text-lg"></i> Về trang chính</a>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf
                <button type="submit" class="flex items-center justify-center gap-2 w-full py-2.5 app-muted hover:text-error text-sm font-medium"><i class="ph ph-sign-out"></i> Đăng xuất</button>
            </form>
        </div>
    </aside>

    <main class="admin-shell flex-grow flex flex-col min-w-0 app-bg relative h-full overflow-hidden">
        <header class="h-16 lg:h-20 flex items-center justify-between px-4 sm:px-8 border-b app-border app-card backdrop-blur-md sticky top-0 z-30 shrink-0">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-btn" type="button" class="lg:hidden app-muted hover:app-text" aria-label="Mở menu" aria-expanded="false" aria-controls="sidebar"><i class="ph ph-list text-2xl"></i></button>
                <p class="text-lg font-bold app-text hidden sm:block">@yield('page-title')</p>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <form method="POST" action="{{ route('admin.cinema-context.update') }}" class="hidden lg:flex items-center gap-2">
                    @csrf
                    <label for="admin-cinema-context" class="sr-only">Ngữ cảnh chi nhánh</label>
                    <select id="admin-cinema-context" name="cinema_id" class="app-input max-w-56 rounded-lg border app-border px-3 py-2 text-sm" onchange="this.form.submit()">
                        @if($adminHasGlobalCinemaAccess)<option value="all" @selected(!$adminCurrentCinema)>Toàn hệ thống</option>@endif
                        @foreach($adminAccessibleCinemas as $contextCinema)
                            <option value="{{ $contextCinema->id }}" @selected($adminCurrentCinema?->id === $contextCinema->id)>{{ $contextCinema->name }}</option>
                        @endforeach
                    </select>
                </form>
                <span class="hidden xl:inline-flex rounded-full border app-border px-3 py-1 text-xs font-bold text-brand-start">{{ $adminCurrentCinema?->name ?? ($adminHasGlobalCinemaAccess ? 'Toàn hệ thống' : 'Chưa phân công') }}</span>
                <div class="relative hidden md:block w-56">
                    <i class="ph ph-magnifying-glass app-muted text-sm absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"></i>
                    <input type="text" class="app-input w-full pl-9 pr-3 py-2 rounded-lg border app-border focus:outline-none focus:border-brand-start transition-colors text-sm" placeholder="Tìm kiếm (Ctrl+K)">
                </div>
                <button type="button" class="relative app-muted hover:app-text transition-colors p-2" aria-label="Thông báo"><i class="ph ph-bell text-lg"></i></button>
                <button data-theme-toggle type="button" class="flex items-center gap-1.5 px-3 py-2 rounded-xl app-card border app-border app-muted hover:border-brand-start transition-all text-sm" aria-label="Đổi giao diện sáng/tối" aria-pressed="false"><span class="theme-icon flex items-center text-base"><i class="ph-fill ph-moon"></i></span><span class="theme-text hidden lg:inline text-xs font-medium">Tối</span></button>
                <div class="flex items-center gap-3 pl-3 border-l app-border">
                    <div class="hidden sm:block text-right">
                        <p class="text-sm font-bold app-text leading-tight">{{ auth()->user()?->name ?? 'Khu vực quản trị' }}</p>
                        <p class="text-[10px] uppercase tracking-wider text-brand-start font-bold">{{ auth()->user()?->role?->display_name ?? 'Chưa có vai trò' }}</p>
                    </div>
                    <span class="w-9 h-9 rounded-full app-bg border app-border flex items-center justify-center text-brand-start"><i class="ph-fill ph-user text-lg"></i></span>
                </div>
            </div>
        </header>
        <div class="flex-grow overflow-y-auto" data-app-scroll-container><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8 pb-12">
            <div class="sm:hidden mb-4"><p class="text-xl font-bold app-heading">@yield('page-title')</p></div>
            <x-flash-messages :error-bag="$errors" :include-validation="! \Illuminate\Support\Facades\View::hasSection('suppress-global-validation-summary')" />
            @yield('content')
        </div></div>
    </main>

    @stack('modals')

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
