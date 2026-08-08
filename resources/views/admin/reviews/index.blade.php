@extends('layouts.admin')

@section('title', 'Quản lý đánh giá - MovieMate')
@section('page-title', 'Quản lý đánh giá')

@section('content')
<div class="admin-page-header">
    <div><h1 class="admin-page-title">Quản lý đánh giá</h1><p class="admin-page-subtitle">Kiểm duyệt đánh giá xác thực theo phạm vi chi nhánh được phân công.</p></div>
</div>

<form method="GET" class="admin-form-card mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_180px_220px_180px_130px_auto]" aria-label="Lọc đánh giá">
    <label><span class="admin-label">Tìm kiếm</span><input name="search" value="{{ $filters['search'] ?? '' }}" class="admin-input" placeholder="Khách hàng, phim, nội dung"></label>
    <label><span class="admin-label">Trạng thái</span><select name="status" class="admin-input"><option value="">Tất cả</option>@foreach(['pending' => 'Chờ duyệt', 'published' => 'Đã đăng', 'hidden' => 'Đã ẩn', 'rejected' => 'Từ chối'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label><span class="admin-label">Phim</span><select name="movie_id" class="admin-input"><option value="">Tất cả phim</option>@foreach($movies as $movie)<option value="{{ $movie->id }}" @selected((string) ($filters['movie_id'] ?? '') === (string) $movie->id)>{{ $movie->title }}</option>@endforeach</select></label>
    <label><span class="admin-label">Chi nhánh</span><select name="cinema_id" class="admin-input"><option value="">Tất cả chi nhánh</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected((string) ($filters['cinema_id'] ?? '') === (string) $cinema->id)>{{ $cinema->name }}</option>@endforeach</select></label>
    <label><span class="admin-label">Điểm</span><select name="rating" class="admin-input"><option value="">1–10</option>@foreach(range(10, 1) as $rating)<option value="{{ $rating }}" @selected((string) ($filters['rating'] ?? '') === (string) $rating)>{{ $rating }}/10</option>@endforeach</select></label>
    <button class="admin-btn-primary self-end">Lọc</button>
</form>

<div class="space-y-4">
    @forelse($reviews as $review)
        <article class="admin-form-card">
            <div class="flex flex-wrap justify-between gap-3">
                <div><h2 class="font-bold app-text">{{ $review->movie->title }} · {{ $review->rating }}/10</h2><p class="text-sm app-muted">{{ $review->user->name }} · {{ $review->booking?->cinema?->name }} · {{ $review->created_at->format('d/m/Y H:i') }} @if($review->is_verified) · ✓ Đã xác thực @endif</p></div>
                <span class="rounded-full app-secondary px-3 py-1 text-xs font-bold">{{ $review->moderation_status_label }}</span>
            </div>
            <p class="mt-3 whitespace-pre-line app-text">{{ $review->comment ?: 'Không có nhận xét.' }}</p>
            @if($review->moderation_flags)
                <p class="mt-2 text-sm text-warning">Cờ tự động: {{ collect($review->moderation_flag_labels)->join(', ') }}</p>
            @endif
            @can('reviews.moderate')
                <form method="POST" action="{{ route('admin.reviews.moderate', $review) }}" class="mt-4 flex flex-wrap gap-2">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="admin-input !w-auto"><option value="published">Duyệt/khôi phục</option><option value="hidden">Ẩn</option><option value="rejected">Từ chối</option></select>
                    <input name="reason" class="admin-input min-w-60 flex-1" placeholder="Lý do (bắt buộc khi ẩn/từ chối)">
                    <button class="admin-btn-primary">Cập nhật</button>
                </form>
            @endcan
        </article>
    @empty
        <x-empty-state title="Chưa có đánh giá" description="Chưa có dữ liệu đánh giá xác thực." icon="ph-star" />
    @endforelse
</div>
{{ $reviews->links() }}
@endsection
