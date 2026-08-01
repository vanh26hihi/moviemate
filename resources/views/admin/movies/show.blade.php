@extends('layouts.admin')

@section('title', 'Chi tiết phim')
@section('page-title', 'Chi tiết phim')

@php
    $statusMeta = [
        'now_showing' => ['label' => 'Đang chiếu', 'class' => 'bg-success/10 text-success border border-success/20'],
        'coming_soon' => ['label' => 'Sắp chiếu', 'class' => 'bg-warning/10 text-warning border border-warning/20'],
        'stopped' => ['label' => 'Ngừng chiếu', 'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20'],
    ];
    $status = $statusMeta[$movie->status] ?? ['label' => $movie->status ?: 'Chưa rõ', 'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20'];
@endphp

@section('content')
<div class="admin-page-header">
    <div>
        <a href="{{ route('admin.movies.index') }}" class="inline-flex items-center gap-2 text-sm font-bold app-text-muted hover:text-brand-start transition-colors mb-3">
            <i class="ph ph-arrow-left"></i>
            Quay lại danh sách
        </a>
        <h1 class="admin-page-title">{{ $movie->title }}</h1>
        <p class="admin-page-subtitle">Thông tin chi tiết, media, thể loại và trạng thái phát hành.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.movies.edit', $movie) }}" class="admin-btn-warning">
            <i class="ph ph-pencil-simple"></i>
            Sửa phim
        </a>
        <form action="{{ route('admin.movies.destroy', $movie) }}" method="POST"
              onsubmit="return confirm('Bạn có chắc muốn xóa phim này?');" class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="admin-btn-danger">
                <i class="ph ph-trash"></i>
                Xóa
            </button>
        </form>
    </div>
</div>

<div class="space-y-6">
    <div class="admin-detail-card overflow-hidden !p-0">
        <div class="relative h-64 overflow-hidden bg-slate-950">
            @if($movie->cover_url)
                <img src="{{ $movie->cover_url }}" alt="{{ $movie->title }}" class="h-full w-full object-cover" loading="lazy">
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
            @else
                <div class="admin-media-fallback h-full w-full text-3xl">
                    <span>MovieMate</span>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 p-5 sm:p-6">
            <div class="lg:col-span-3">
                <div class="mx-auto max-w-[220px] overflow-hidden rounded-2xl border app-border bg-slate-950 shadow-2xl shadow-black/20">
                    <div class="aspect-[2/3]">
                        @if($movie->poster_url)
                            <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <div class="admin-media-fallback h-full w-full text-2xl">MM</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:col-span-9">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <span class="admin-badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                    @foreach($movie->genres as $genre)
                        <span class="admin-badge bg-brand-start/10 text-brand-start border border-brand-start/20">{{ $genre->name }}</span>
                    @endforeach
                </div>

                <h2 class="text-2xl sm:text-3xl font-extrabold app-heading">{{ $movie->title }}</h2>
                <p class="mt-2 font-mono text-sm app-text-muted">{{ $movie->slug }}</p>

                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div class="rounded-2xl app-card-soft border app-border p-4">
                        <p class="text-xs font-bold uppercase tracking-wider app-text-muted">Quốc gia</p>
                        <p class="mt-1 font-bold app-text">{{ $movie->country ?? 'Chưa cập nhật' }}</p>
                    </div>
                    <div class="rounded-2xl app-card-soft border app-border p-4">
                        <p class="text-xs font-bold uppercase tracking-wider app-text-muted">Thời lượng</p>
                        <p class="mt-1 font-bold app-text">{{ $movie->duration ?? 'N/A' }} phút</p>
                    </div>
                    <div class="rounded-2xl app-card-soft border app-border p-4">
                        <p class="text-xs font-bold uppercase tracking-wider app-text-muted">Độ tuổi</p>
                        <p class="mt-1 font-bold app-text">{{ $movie->age_rating ?? 'N/A' }}</p>
                    </div>
                    <div class="rounded-2xl app-card-soft border app-border p-4">
                        <p class="text-xs font-bold uppercase tracking-wider app-text-muted">Ngày khởi chiếu</p>
                        <p class="mt-1 font-bold app-text">{{ $movie->release_date ? \Carbon\Carbon::parse($movie->release_date)->format('d/m/Y') : 'Chưa cập nhật' }}</p>
                    </div>
                    <div class="rounded-2xl app-card-soft border app-border p-4">
                        <p class="text-xs font-bold uppercase tracking-wider app-text-muted">Poster</p>
                        <p class="mt-1 font-bold app-text">{{ $movie->poster_url ? 'Đã có' : 'Chưa có' }}</p>
                    </div>
                    <div class="rounded-2xl app-card-soft border app-border p-4">
                        <p class="text-xs font-bold uppercase tracking-wider app-text-muted">Cover</p>
                        <p class="mt-1 font-bold app-text">{{ $movie->cover_url ? 'Đã có' : 'Chưa có' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 admin-detail-card">
            <h3 class="text-lg font-extrabold app-heading mb-3">Mô tả phim</h3>
            <p class="app-text-muted leading-relaxed whitespace-pre-line">{{ $movie->description ?? 'Chưa có mô tả.' }}</p>
        </section>

        <section class="admin-detail-card">
            <h3 class="text-lg font-extrabold app-heading mb-3">Trailer</h3>
            @if($movie->trailer_url)
                <p class="app-text-muted text-sm mb-4">Mở trailer trong tab mới để kiểm tra nội dung hiển thị.</p>
                <a href="{{ $movie->trailer_url }}" target="_blank" rel="noopener noreferrer" class="admin-btn-primary w-full">
                    <i class="ph-fill ph-play"></i>
                    Xem trailer
                </a>
            @else
                <div class="rounded-2xl app-card-soft border app-border p-5 text-center app-text-muted">
                    Chưa có trailer.
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
