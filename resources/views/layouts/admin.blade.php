<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Khu vực quản trị MovieMate')</title>
    <x-brand.head-icons />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            var theme = localStorage.getItem('theme') || localStorage.getItem('moviemate_theme') || 'dark';
            document.documentElement.classList.toggle('light', theme === 'light');
        })();
    </script>
</head>
<body class="app-page font-sans antialiased flex h-screen overflow-hidden">
    <a href="#admin-main-content" class="sr-only focus:not-sr-only fixed left-4 top-4 z-[100] rounded-xl bg-brand-start px-4 py-3 font-bold text-white shadow-xl focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-brand-start">Bỏ qua điều hướng, đến nội dung chính</a>
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden hidden" aria-hidden="true"></div>

    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-64 app-sidebar border-r app-border transform -translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col h-full overflow-hidden" tabindex="-1" data-admin-mobile-drawer aria-label="Điều hướng quản trị">
        <div class="h-16 lg:h-20 flex items-center px-6 border-b app-border shrink-0" data-admin-sidebar-logo>
            <a href="{{ route('home') }}" class="min-w-0" aria-label="MovieMate - Trang chủ">
                <x-brand.logo class="brand-logo--sidebar" />
                <span class="mt-0.5 block text-[10px] font-bold uppercase tracking-widest app-muted">{{ $adminHasGlobalCinemaAccess ? 'Quản trị toàn chuỗi' : 'Vận hành chi nhánh' }}</span>
            </a>
        </div>

        <div class="border-b app-border px-4 py-3 lg:hidden">
            <form method="POST" action="{{ route('admin.cinema-context.update') }}">
                @csrf
                <label for="admin-cinema-context-mobile" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider app-muted">{{ $adminHasGlobalCinemaAccess ? 'Phạm vi quản trị' : 'Chi nhánh hiện tại' }}</label>
                <select id="admin-cinema-context-mobile" name="cinema_id" class="app-input w-full rounded-lg border app-border px-3 py-2 text-sm" onchange="this.form.submit()">
                    @if($adminHasGlobalCinemaAccess)<option value="all" @selected(!$adminCurrentCinema)>Toàn hệ thống</option>@endif
                    @foreach($adminAccessibleCinemas as $contextCinema)
                        <option value="{{ $contextCinema->id }}" @selected($adminCurrentCinema?->id === $contextCinema->id)>{{ $contextCinema->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @php
            $navItems = [
                'dashboard' => ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'permission' => 'dashboard.view', 'label' => 'Tổng quan', 'icon' => 'ph-squares-four'],
                'cinemas' => ['route' => 'admin.cinemas.index', 'active' => ['admin.cinema.*', 'admin.cinemas.*'], 'permission' => 'cinemas.view', 'label' => 'Chi nhánh', 'icon' => 'ph-buildings'],
                'showtimes' => ['route' => 'admin.showtimes.index', 'active' => 'admin.showtimes.*', 'permission' => 'showtimes.view', 'label' => 'Lịch vận hành', 'icon' => 'ph-calendar-plus'],
                'rooms' => ['route' => 'admin.rooms.index', 'active' => ['admin.rooms.*', 'admin.seats.*'], 'permission' => 'rooms.view', 'label' => 'Phòng chiếu', 'icon' => 'ph-projector-screen'],
                'bookings' => ['route' => 'admin.bookings.index', 'active' => 'admin.bookings.*', 'permission' => 'bookings.view', 'label' => 'Đơn đặt vé', 'icon' => 'ph-ticket'],
                'foodOrders' => ['route' => 'admin.food-orders.index', 'active' => 'admin.food-orders.*', 'permission' => 'food-orders.view', 'label' => 'Đơn đồ ăn tại rạp', 'icon' => 'ph-shopping-bag'],
                'movies' => ['route' => 'admin.movies.index', 'active' => ['admin.movies.*', 'admin.genres.*'], 'permission' => 'movies.view', 'label' => 'Phim', 'icon' => 'ph-film-slate'],
                'priceBooks' => ['route' => 'admin.price-books.index', 'active' => 'admin.price-books.*', 'permission' => 'pricing.view', 'label' => 'Bảng giá', 'icon' => 'ph-currency-circle-dollar'],
                'discounts' => ['route' => 'admin.discounts.index', 'active' => 'admin.discounts.*', 'permission' => 'discounts.view', 'label' => 'Khuyến mãi', 'icon' => 'ph-ticket-percent'],
                'foods' => ['route' => 'admin.foods.index', 'active' => 'admin.foods.*', 'permission' => 'foods.view', 'label' => 'Món ăn', 'icon' => 'ph-burger'],
                'reviews' => ['route' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'permission' => 'reviews.view', 'label' => 'Đánh giá phim', 'icon' => 'ph-star'],
                'payments' => ['route' => 'admin.payments.index', 'active' => 'admin.payments.*', 'permission' => 'payments.view', 'label' => 'Thanh toán', 'icon' => 'ph-credit-card'],
                'reports' => ['route' => 'admin.reports.index', 'active' => 'admin.reports.*', 'permission' => 'reports.view', 'label' => 'Báo cáo', 'icon' => 'ph-chart-line-up'],
                'roomTypes' => ['route' => 'admin.room-types.index', 'active' => 'admin.room-types.*', 'permission' => 'room_types.view', 'label' => 'Loại phòng', 'icon' => 'ph-stack'],
                'layoutTemplates' => ['route' => 'admin.layout-templates.index', 'active' => 'admin.layout-templates.*', 'permission' => 'layout_templates.view', 'label' => 'Mẫu sơ đồ', 'icon' => 'ph-grid-four'],
                'presentationFormats' => ['route' => 'admin.presentation-formats.index', 'active' => 'admin.presentation-formats.*', 'permission' => 'presentation_formats.view', 'label' => 'Định dạng trình chiếu', 'icon' => 'ph-cube-focus'],
                'users' => ['route' => 'admin.users.index', 'active' => 'admin.users.*', 'permission' => 'users.view', 'label' => 'Người dùng', 'icon' => 'ph-users'],
                'roles' => ['route' => 'admin.roles.index', 'active' => 'admin.roles.*', 'permission' => 'roles.view', 'label' => 'Vai trò và quyền', 'icon' => 'ph-shield-check'],
                'activityLogs' => ['route' => 'admin.activity-logs.index', 'active' => 'admin.activity-logs.*', 'permission' => 'activity_logs.view', 'label' => 'Nhật ký hoạt động', 'icon' => 'ph-list-magnifying-glass'],
            ];

            $adminNavigation = $adminHasGlobalCinemaAccess ? [
                ['label' => 'Tổng quan', 'items' => [$navItems['dashboard']]],
                ['label' => 'Vận hành', 'items' => [$navItems['cinemas'], $navItems['showtimes'], $navItems['rooms'], $navItems['bookings'], $navItems['foodOrders']]],
                ['label' => 'Kinh doanh', 'items' => [$navItems['movies'], $navItems['priceBooks'], $navItems['discounts'], $navItems['foods']]],
                ['label' => 'Khách hàng', 'items' => [$navItems['reviews']]],
                ['label' => 'Tài chính', 'items' => [$navItems['payments'], $navItems['reports']]],
                ['label' => 'Cấu hình', 'items' => [$navItems['roomTypes'], $navItems['layoutTemplates'], $navItems['presentationFormats'], $navItems['users'], $navItems['roles'], $navItems['activityLogs']]],
            ] : [
                ['label' => 'Tổng quan chi nhánh', 'items' => [[...$navItems['dashboard'], 'label' => 'Tổng quan chi nhánh']]],
                ['label' => 'Vận hành', 'items' => [
                    $navItems['showtimes'], $navItems['rooms'], $navItems['bookings'],
                    [...$navItems['payments'], 'label' => 'Thanh toán chi nhánh'],
                    $navItems['foodOrders'],
                    [...$navItems['users'], 'label' => 'Nhân sự chi nhánh'],
                ]],
                ['label' => 'Kinh doanh', 'items' => [
                    [...$navItems['movies'], 'label' => 'Danh mục phim · Chỉ xem'],
                    [...$navItems['priceBooks'], 'label' => 'Bảng giá áp dụng'],
                    $navItems['discounts'],
                    [...$navItems['foods'], 'label' => 'Món ăn dùng chung · Chỉ xem'],
                ]],
                ['label' => 'Báo cáo', 'items' => [[...$navItems['reports'], 'label' => 'Báo cáo chi nhánh']]],
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

    <main id="admin-main-content" class="admin-shell flex-grow flex flex-col min-w-0 app-bg relative h-full overflow-hidden" tabindex="-1">
        <header class="h-16 lg:h-20 flex items-center justify-between px-4 sm:px-8 border-b app-border app-card backdrop-blur-md sticky top-0 z-30 shrink-0">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-btn" type="button" class="lg:hidden rounded-lg p-2 app-muted hover:app-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-start" aria-label="Mở menu" aria-expanded="false" aria-controls="sidebar"><i class="ph ph-list text-2xl" aria-hidden="true"></i></button>
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
            <div class="mb-4 flex min-w-0 flex-wrap items-start gap-x-3 gap-y-1 rounded-xl border app-border app-card-soft px-4 py-3 text-sm" data-admin-context-banner>
                <span class="shrink-0 font-semibold app-muted">{{ $adminHasGlobalCinemaAccess ? 'Phạm vi quản trị' : 'Chi nhánh hiện tại' }}</span>
                <strong class="min-w-0 break-words app-text" data-admin-context-name title="{{ $adminCurrentCinema?->name ?? ($adminHasGlobalCinemaAccess ? 'Toàn hệ thống' : 'Chưa phân công') }}">{{ $adminCurrentCinema?->name ?? ($adminHasGlobalCinemaAccess ? 'Toàn hệ thống' : 'Chưa phân công') }}</strong>
            </div>
            <div class="sm:hidden mb-4"><p class="text-xl font-bold app-heading">@yield('page-title')</p></div>
            <x-flash-messages :error-bag="$errors" :include-validation="! \Illuminate\Support\Facades\View::hasSection('suppress-global-validation-summary')" />
            <x-form-validation-state :errors="$errors" />
            @yield('content')
        </div></div>
    </main>

    @stack('modals')

    <script>
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const toggleBtn = document.getElementById('mobile-menu-btn');
        const adminMain = document.getElementById('admin-main-content');
        const desktopSidebar = window.matchMedia('(min-width: 1024px)');
        const drawerFocusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

        function drawerFocusableElements() {
            return Array.from(sidebar.querySelectorAll(drawerFocusableSelector))
                .filter((element) => element.getClientRects().length > 0);
        }

        function isSidebarOpen() {
            return !desktopSidebar.matches && toggleBtn.getAttribute('aria-expanded') === 'true';
        }

        function openSidebar() {
            if (desktopSidebar.matches) return;

            sidebar.classList.remove('-translate-x-full');
            sidebar.removeAttribute('aria-hidden');
            sidebar.inert = false;
            backdrop.classList.remove('hidden');
            toggleBtn.setAttribute('aria-expanded', 'true');
            toggleBtn.setAttribute('aria-label', 'Đóng menu');
            adminMain.inert = true;
            adminMain.setAttribute('aria-hidden', 'true');
            window.requestAnimationFrame(() => (drawerFocusableElements()[0] || sidebar).focus());
        }

        function closeSidebar(restoreFocus = true) {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.setAttribute('aria-label', 'Mở menu');
            adminMain.inert = false;
            adminMain.removeAttribute('aria-hidden');

            if (restoreFocus && !desktopSidebar.matches) toggleBtn.focus();

            if (desktopSidebar.matches) {
                sidebar.inert = false;
                sidebar.removeAttribute('aria-hidden');
            } else {
                sidebar.inert = true;
                sidebar.setAttribute('aria-hidden', 'true');
            }
        }

        function syncSidebarViewport() {
            closeSidebar(false);
        }

        if (toggleBtn && sidebar && backdrop && adminMain) {
            toggleBtn.addEventListener('click', () => isSidebarOpen() ? closeSidebar() : openSidebar());
            backdrop.addEventListener('click', () => closeSidebar());
            document.addEventListener('keydown', (event) => {
                if (!isSidebarOpen()) return;

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeSidebar();
                    return;
                }

                if (event.key !== 'Tab') return;
                const focusable = drawerFocusableElements();
                if (focusable.length === 0) {
                    event.preventDefault();
                    sidebar.focus();
                    return;
                }

                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (!sidebar.contains(document.activeElement)) {
                    event.preventDefault();
                    (event.shiftKey ? last : first).focus();
                } else if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
            if (typeof desktopSidebar.addEventListener === 'function') desktopSidebar.addEventListener('change', syncSidebarViewport);
            else desktopSidebar.addListener(syncSidebarViewport);
            syncSidebarViewport();
        }
    </script>
    @stack('scripts')
</body>
</html>
