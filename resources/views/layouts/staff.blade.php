<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'MovieMate Staff')</title>
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
        <a href="/staff/dashboard" class="mb-10 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#FF3D57] to-[#FF7A18]">ðŸŽŸï¸</div>
            <div>
                <h1 class="text-xl font-black">Movie<span class="text-[#FF7A18]">Mate</span></h1>
                <p class="text-xs text-gray-400">Staff Panel</p>
            </div>
        </a>

        <nav class="space-y-2 text-sm font-semibold text-gray-300">
            <a href="/staff/dashboard" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Dashboard</a>
            <a href="/staff/tickets/check" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Kiá»ƒm tra vÃ© QR</a>
            <a href="/staff/tickets" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Danh sÃ¡ch vÃ©</a>
            <a href="/staff/counter-sale" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">BÃ¡n vÃ© táº¡i quáº§y</a>
            <a href="/" class="block rounded-2xl px-4 py-3 hover:bg-white/10 hover:text-white">Vá» website</a>
        </nav>
    </aside>

    <div class="min-h-screen flex-1 lg:ml-72">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-[#080A12]/80 px-6 py-4 backdrop-blur-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-black">@yield('page-title', 'Staff Panel')</h2>
                    <p class="text-sm text-gray-400">Quáº£n lÃ½ soÃ¡t vÃ© vÃ  bÃ¡n vÃ© táº¡i ráº¡p</p>
                </div>

                <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-2">
                    <div class="h-9 w-9 rounded-full bg-gradient-to-br from-[#FF3D57] to-[#FF7A18]"></div>
                    <div>
                        <p class="text-sm font-bold">NhÃ¢n viÃªn</p>
                        <p class="text-xs text-gray-400">Staff</p>
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
