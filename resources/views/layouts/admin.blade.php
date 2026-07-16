<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'MovieMate Admin')</title>
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

<div class="flex min-h-screen">

    <aside class="fixed left-0 top-0 hidden h-screen w-72 border-r border-white/10 bg-[#0B0F1A] p-6 lg:block">
        <a href="/admin/dashboard" class="mb-10 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#FF3D57] to-[#FF7A18]">ðŸŽ¬</div>
            <div>
                <h1 class="text-xl font-black">Movie<span class="text-[#FF7A18]">Mate</span></h1>
                <p class="text-xs text-gray-400">Admin Panel</p>
            </div>
        </a>

        <nav class="space-y-1 text-sm font-semibold text-gray-300">
            <a href="/admin/dashboard" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Dashboard</a>
            <a href="/admin/movies" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Quáº£n lÃ½ phim</a>
            <a href="/admin/genres" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Thá»ƒ loáº¡i</a>
            <a href="/admin/cinemas" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Ráº¡p chiáº¿u</a>
            <a href="/admin/rooms" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">PhÃ²ng chiáº¿u</a>
            <a href="/admin/seats" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Gháº¿</a>
            <a href="/admin/showtimes" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Suáº¥t chiáº¿u</a>
            <a href="/admin/bookings" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">VÃ© Ä‘áº·t</a>
            <a href="/admin/users" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">NgÆ°á»i dÃ¹ng</a>
            <a href="/admin/reviews" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">ÄÃ¡nh giÃ¡</a>
            <a href="/admin/analytics/revenue" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Doanh thu</a>
            <a href="/admin/analytics/top-movies" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Phim bÃ¡n cháº¡y</a>
            <a href="/admin/ai/movie-content" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">AI Tools</a>
            <a href="/" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Vá» website</a>
        </nav>
    </aside>

    <div class="min-h-screen flex-1 lg:ml-72">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-[#080A12]/80 px-6 py-4 backdrop-blur-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-black">@yield('page-title', 'Admin Panel')</h2>
                    <p class="text-sm text-gray-400">Quáº£n lÃ½ toÃ n bá»™ há»‡ thá»‘ng MovieMate</p>
                </div>

                <div class="flex items-center gap-4">
                    <input type="text" placeholder="TÃ¬m kiáº¿m..." class="hidden h-11 rounded-2xl border border-white/10 bg-[#151A27] px-4 text-sm outline-none focus:border-[#FF7A18] md:block">
                    <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-2">
                        <div class="h-9 w-9 rounded-full bg-gradient-to-br from-[#7C3AED] to-[#2563EB]"></div>
                        <div>
                            <p class="text-sm font-bold">Admin</p>
                            <p class="text-xs text-gray-400">Quáº£n trá»‹ viÃªn</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-6">
            @yield('content')
        </main>
    </div>

</div>

</body>
</html>
