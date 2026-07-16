<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - MovieMate</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        (function() {
            var t = localStorage.getItem('theme') || localStorage.getItem('moviemate_theme') || 'dark';
            if (t === 'light') document.documentElement.classList.add('light');
            else document.documentElement.classList.remove('light');
        })();
    </script>
</head>
<body class="app-page font-sans antialiased min-h-screen flex items-center justify-center p-4 overflow-x-hidden overflow-y-auto relative">

    <button data-theme-toggle type="button"
        class="fixed top-4 right-4 z-30 flex items-center gap-1.5 px-3 py-2 rounded-xl bg-dark-card/80 backdrop-blur border border-dark-border text-text-sub hover:text-text-main hover:border-brand-start transition-all text-sm"
        aria-label="Đổi giao diện sáng/tối" aria-pressed="false">
        <span class="theme-icon flex items-center text-base"><i class="ph-fill ph-moon"></i></span>
        <span class="theme-text hidden sm:inline text-xs font-medium">Tối</span>
    </button>
    
    <!-- Background Effects -->
    <div class="absolute inset-0 z-0">
        <div class="absolute top-0 right-0 w-[800px] h-[800px] bg-brand-start/10 rounded-full blur-[150px] -translate-y-1/2 translate-x-1/3"></div>
        <div class="absolute bottom-0 left-0 w-[600px] h-[600px] bg-ai-start/10 rounded-full blur-[120px] translate-y-1/3 -translate-x-1/3"></div>
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
    </div>

    <div class="w-full max-w-md relative z-20">
        
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-[2rem] bg-dark-card border border-dark-border shadow-2xl mb-6 relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-tr from-brand-start/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <i class="ph-fill ph-film-strip text-5xl text-brand-start"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2 tracking-tight">MovieMate Admin</h1>
            <p class="text-text-sub">Hệ thống quản trị đặt vé xem phim</p>
        </div>

        <div class="bg-dark-card/80 backdrop-blur-xl border border-dark-border rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <!-- Top line highlight -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-brand-start to-brand-end"></div>

            <form action="{{ route('login.post') }}" method="POST" class="space-y-6">
                @csrf
                
                <div>
                    <label for="email" class="block text-sm font-medium text-text-sub mb-2">Email quản trị</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="ph ph-envelope-simple text-text-sub text-lg"></i>
                        </div>
                        <input type="email" id="email" name="email" value="{{ old('email', 'admin@example.com') }}" class="w-full pl-11 pr-4 py-3.5 bg-dark-main border border-dark-border rounded-xl text-white focus:outline-none focus:border-brand-start focus:ring-1 focus:ring-brand-start transition-colors placeholder-text-sub/50" placeholder="admin@example.com" required>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label for="password" class="block text-sm font-medium text-text-sub">Mật khẩu</label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="ph ph-lock-key text-text-sub text-lg"></i>
                        </div>
                        <input type="password" id="password" name="password" class="w-full pl-11 pr-11 py-3.5 bg-dark-main border border-dark-border rounded-xl text-white focus:outline-none focus:border-brand-start focus:ring-1 focus:ring-brand-start transition-colors placeholder-text-sub/50" placeholder="••••••••" required>
                        <button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-text-sub hover:text-white transition-colors">
                            <i class="ph ph-eye text-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <div class="relative flex items-center justify-center w-5 h-5 rounded bg-dark-main border border-dark-border group-hover:border-brand-start transition-colors">
                            <input type="checkbox" name="remember" value="1" class="peer sr-only">
                            <i class="ph-bold ph-check text-white text-xs opacity-0 peer-checked:opacity-100 transition-opacity"></i>
                            <div class="absolute inset-0 rounded bg-brand-start opacity-0 peer-checked:opacity-100 -z-10 transition-opacity"></div>
                        </div>
                        <span class="text-sm text-text-sub group-hover:text-white transition-colors">Ghi nhớ đăng nhập</span>
                    </label>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full flex justify-center items-center gap-2 py-4 px-4 rounded-xl font-bold text-white bg-gradient-to-r from-brand-start to-brand-end hover:shadow-lg hover:shadow-brand-start/25 transition-all transform hover:-translate-y-0.5">
                        Đăng nhập Dashboard <i class="ph-bold ph-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>

    </div>
</body>
</html>
