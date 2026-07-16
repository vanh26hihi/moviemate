<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'MovieMate - Đặt vé xem phim thông minh')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Be Vietnam Pro', sans-serif;
        }
    </style>
</head>

<body class="bg-[#080A12] text-white">

    <header class="fixed top-0 left-0 right-0 z-50 border-b border-white/10 bg-[#080A12]/80 backdrop-blur-xl">
        <div class="mx-auto flex h-20 max-w-[1440px] items-center justify-between px-6 lg:px-10">

            <a href="/" class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#FF3D57] to-[#FF7A18] shadow-lg shadow-red-500/30">
                    🎬
                </div>

                <div>
                    <h1 class="text-xl font-black">
                        Movie<span class="text-[#FF7A18]">Mate</span>
                    </h1>
                    <p class="text-[11px] text-gray-400">AI Cinema Booking</p>
                </div>
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-gray-300 lg:flex">
                <a href="/" class="transition hover:text-[#FF7A18]">Trang chủ</a>
                <a href="/movies" class="transition hover:text-[#FF7A18]">Phim</a>
                <a href="/movies/1#showtimes" class="transition hover:text-[#FF7A18]">Lịch chiếu</a>
                <a href="/ai/recommend" class="transition hover:text-[#7C3AED]">AI gợi ý</a>
                <a href="/booking-history" class="transition hover:text-[#FF7A18]">Vé của tôi</a>
                <a href="/staff/dashboard" class="transition hover:text-[#FF7A18]">Staff</a>
                <a href="/admin/dashboard" class="transition hover:text-[#FF7A18]">Admin</a>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <a href="/login" class="rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold transition hover:border-[#FF7A18] hover:text-[#FF7A18]">
                    Đăng nhập
                </a>

                <a href="/register" class="rounded-xl bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-red-500/30 transition hover:scale-105">
                    Đăng ký
                </a>
            </div>

            <button class="lg:hidden rounded-xl border border-white/10 p-3">
                ☰
            </button>
        </div>
    </header>

    <main class="pt-20">
        @yield('content')
    </main>

    <footer class="border-t border-white/10 bg-[#0B0F1A]">
        <div class="mx-auto grid max-w-[1440px] gap-10 px-6 py-12 md:grid-cols-4 lg:px-10">

            <div>
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#FF3D57] to-[#FF7A18]">
                        🎬
                    </div>
                    <h2 class="text-xl font-black">
                        Movie<span class="text-[#FF7A18]">Mate</span>
                    </h2>
                </div>

                <p class="text-sm leading-6 text-gray-400">
                    Nền tảng đặt vé xem phim trực tuyến tích hợp AI gợi ý phim và chatbot hỗ trợ khách hàng.
                </p>
            </div>

            <div>
                <h3 class="mb-4 font-bold">Khám phá</h3>
                <ul class="space-y-3 text-sm text-gray-400">
                    <li>Phim đang chiếu</li>
                    <li>Phim sắp chiếu</li>
                    <li>Lịch chiếu</li>
                    <li>Ưu đãi</li>
                </ul>
            </div>

            <div>
                <h3 class="mb-4 font-bold">Hỗ trợ</h3>
                <ul class="space-y-3 text-sm text-gray-400">
                    <li>Hướng dẫn đặt vé</li>
                    <li>Chính sách vé</li>
                    <li>Chatbot AI</li>
                    <li>Liên hệ</li>
                </ul>
            </div>

            <div>
                <h3 class="mb-4 font-bold">Liên hệ</h3>
                <ul class="space-y-3 text-sm text-gray-400">
                    <li>Email: support@moviemate.vn</li>
                    <li>Hotline: 1900 9999</li>
                    <li>Địa chỉ: Hà Nội, Việt Nam</li>
                </ul>
            </div>
        </div>

        <div class="border-t border-white/10 py-5 text-center text-sm text-gray-500">
            © 2026 MovieMate. All rights reserved.
        </div>
    </footer>

</body>
</html>