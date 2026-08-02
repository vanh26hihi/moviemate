@extends('layouts.user')

@section('title', 'Lịch sử đặt vé - MovieMate')

@section('content')
<section class="cinema-surface min-h-screen py-12">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-8"><p class="mb-2 text-sm font-extrabold uppercase tracking-[0.24em] text-brand-start">History</p><h1 class="text-3xl font-extrabold app-text sm:text-4xl">Lịch sử đặt vé</h1><p class="mt-3 app-muted">Các vé đã đặt sẽ được hiển thị tại đây.</p></div>
        <div class="cinema-card rounded-3xl p-8 text-center sm:p-12">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"><i class="ph-fill ph-ticket text-3xl"></i></div>
            <h2 class="mt-5 text-2xl font-extrabold app-text">Chưa có dữ liệu lịch sử</h2>
            <p class="mx-auto mt-2 max-w-lg app-muted">TEAM chưa truyền danh sách lịch sử đặt vé cho trang này, vì vậy giao diện hiển thị empty state an toàn.</p>
            <a href="{{ route('user.movies.index') }}" class="btn-primary mt-6"><i class="ph-fill ph-film-strip"></i> Khám phá phim</a>
        </div>
    </div>
</section>
@endsection
