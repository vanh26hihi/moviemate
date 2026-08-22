@extends('layouts.user')

@section('title', 'Đánh giá của tôi - MovieMate')

@section('content')
<main class="user-page-shell px-4 py-10">
    <div class="mx-auto max-w-4xl">
        <h1 class="text-3xl font-extrabold app-text">Đánh giá của tôi</h1>
        <div class="mt-6 space-y-4">
            @forelse($reviews as $review)
                <article class="cinema-card flex gap-4 rounded-2xl p-5">
                    <div class="w-16 shrink-0">
                        <div class="poster-frame rounded-xl">
                            @if($review->movie->poster_url)
                                <img src="{{ $review->movie->poster_url }}" alt="Poster {{ $review->movie->title }}" loading="lazy">
                            @else
                                <div class="fallback-poster"><i class="ph-fill ph-film-slate"></i></div>
                            @endif
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex justify-between gap-3">
                            <h2 class="font-bold app-text">{{ $review->movie->title }}</h2>
                            <strong class="text-brand-start">{{ $review->rating }}/10</strong>
                        </div>
                        <p class="mt-2 line-clamp-3 whitespace-pre-line app-muted">{{ $review->comment ?: 'Không có nhận xét.' }}</p>
                        <p class="mt-2 text-xs app-muted">
                            {{ $review->created_at->format('d/m/Y H:i') }} · {{ $review->moderation_status_label }}
                        </p>
                        <a class="mt-3 inline-block text-sm font-bold text-brand-start" href="{{ route('user.movies.show', $review->movie->slug) }}#reviews">
                            {{ $review->moderation_status === 'rejected' ? 'Chỉnh sửa đánh giá' : 'Xem hoặc chỉnh sửa' }}
                        </a>
                    </div>
                </article>
            @empty
                <p class="cinema-card rounded-2xl p-8 text-center app-muted">Bạn chưa có đánh giá nào.</p>
            @endforelse
        </div>
        {{ $reviews->links() }}
    </div>
</main>
@endsection
