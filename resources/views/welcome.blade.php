@extends('layouts.user')

@section('title', 'MovieMate - Trải nghiệm điện ảnh')

@section('content')
<section class="relative min-h-[70vh] overflow-hidden flex items-center justify-center px-4 py-20">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_20%,rgba(255,61,87,0.22),transparent_34%),radial-gradient(circle_at_82%_72%,rgba(108,43,217,0.2),transparent_38%),linear-gradient(135deg,#080a12,#111526)]"></div>
    <div class="absolute inset-0 opacity-20 bg-[linear-gradient(rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.04)_1px,transparent_1px)] bg-[size:48px_48px]"></div>

    <div class="relative z-10 max-w-3xl mx-auto text-center">
        <div class="w-20 h-20 mx-auto mb-7 rounded-3xl bg-gradient-to-br from-brand-start to-brand-end flex items-center justify-center shadow-2xl shadow-brand-start/30 rotate-3">
            <i class="ph-fill ph-film-strip text-white text-4xl"></i>
        </div>
        <p class="text-brand-start text-xs font-extrabold tracking-[0.3em] uppercase mb-4">MovieMate Cinema</p>
        <h1 class="text-4xl sm:text-6xl font-extrabold text-white leading-tight mb-5">Thế giới điện ảnh<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-start to-brand-end">trong tầm tay bạn</span></h1>
        <p class="text-text-sub text-base sm:text-lg max-w-2xl mx-auto mb-9">Khám phá phim đang chiếu và chọn suất chiếu phù hợp tại MovieMate.</p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            @if (Route::has('user.movies.index'))
                <a href="{{ route('user.movies.index') }}" class="w-full sm:w-auto px-7 py-3.5 rounded-xl bg-gradient-to-r from-brand-start to-brand-end text-white font-bold hover:shadow-xl hover:shadow-brand-start/25 transition-all">
                    <i class="ph-bold ph-film-slate mr-2"></i>Xem phim
                </a>
            @endif
            @if (Route::has('home'))
                <a href="{{ route('home') }}" class="w-full sm:w-auto px-7 py-3.5 rounded-xl border border-dark-border bg-dark-card/70 text-white font-bold hover:border-brand-start/60 transition-colors">Về trang chủ</a>
            @endif
        </div>
    </div>
</section>
@endsection
