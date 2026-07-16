<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - MovieMate</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="app-page font-sans antialiased min-h-screen flex items-center justify-center p-4 overflow-x-hidden overflow-y-auto relative">

    <button data-theme-toggle type="button"
        class="fixed top-4 right-4 z-30 flex items-center gap-1.5 px-3 py-2 rounded-xl bg-dark-card/80 backdrop-blur border border-dark-border text-text-sub hover:text-text-main hover:border-brand-start transition-all text-sm"
        aria-label="Đổi giao diện sáng/tối" aria-pressed="false">
        <span class="theme-icon flex items-center text-base"><i class="ph-fill ph-moon"></i></span>
        <span class="theme-text hidden sm:inline text-xs font-medium">Tối</span>

        <!-- Background Effects -->
    <div class="absolute inset-0 z-0">
        <div class="absolute top-0 right-0 w-[800px] h-[800px] bg-brand-start/10 rounded-full blur-[150px] -translate-y-1/2 translate-x-1/3"></div>
        <div class="absolute bottom-0 left-0 w-[600px] h-[600px] bg-ai-start/10 rounded-full blur-[120px] translate-y-1/3 -translate-x-1/3"></div>
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
    </div>
    </button>
    
</body>
</html>