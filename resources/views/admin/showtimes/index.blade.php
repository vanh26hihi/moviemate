@extends('layouts.admin')

@section('title', 'Quản lý suất chiếu - MovieMate Admin')
@section('page-title', 'Quản lý suất chiếu')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">Showtimes</p>
            <h1 class="text-3xl font-extrabold app-text">Suất chiếu</h1>
            <p class="app-muted mt-2">Lên lịch chiếu theo phim, rạp, phòng và trạng thái vận hành.</p>
        </div>
        <a href="{{ route('admin.showtimes.create') }}" class="btn-primary">
            <i class="ph-bold ph-plus"></i> Thêm suất chiếu
        </a>
    </div>

    <div class="cinema-card overflow-hidden">
        <div class="p-5 border-b app-border">
            <form method="GET" action="{{ route('admin.showtimes.index') }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-[180px_1fr_160px_160px_auto] gap-3">
                <select name="cinema_id" class="cinema-input">
                    <option value="">Tất cả rạp</option>
                    @foreach($cinemas as $cinema)
                        <option value="{{ $cinema->id }}" {{ request('cinema_id') == $cinema->id ? 'selected' : '' }}>{{ $cinema->name }}</option>
                    @endforeach
                </select>

                <select name="movie_id" class="cinema-input">
                    <option value="">Tất cả phim</option>
                    @foreach($movies as $movie)
                        <option value="{{ $movie->id }}" {{ request('movie_id') == $movie->id ? 'selected' : '' }}>{{ $movie->title }}</option>
                    @endforeach
                </select>

                <input type="date" name="show_date" value="{{ request('show_date') }}" class="cinema-input">

                <select name="status" class="cinema-input">
                    <option value="">Trạng thái</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Đang chiếu</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
                    <option value="finished" {{ request('status') == 'finished' ? 'selected' : '' }}>Đã chiếu xong</option>
                </select>

                <button type="submit" class="btn-secondary">
                    <i class="ph ph-funnel"></i> Lọc
                </button>
            </form>
            <p class="text-xs app-muted mt-4">Hiển thị <span class="app-text font-bold">{{ $showtimes->total() }}</span> suất chiếu</p>
        </div>

        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Giờ chiếu</th>
                        <th>Phim</th>
                        <th>Rạp / Phòng</th>
                        <th class="text-right">Giá thường/VIP</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($showtimes as $showtime)
                        <tr>
                            <td>
                                <span class="font-extrabold app-text text-base block">{{ $showtime->show_time ? \Carbon\Carbon::parse($showtime->show_time)->format('H:i') : '--:--' }}</span>
                                <span class="text-xs app-muted">{{ $showtime->show_date ? \Carbon\Carbon::parse($showtime->show_date)->format('d/m/Y') : 'Đang cập nhật' }}</span>
                            </td>
                            <td>
                                <span class="font-extrabold app-text text-sm block truncate max-w-[240px]">{{ $showtime->movie->title }}</span>
                            </td>
                            <td>
                                <span class="app-text text-sm font-bold block">{{ $showtime->cinema->name }}</span>
                                <span class="text-xs app-muted">{{ $showtime->room->name }}</span>
                            </td>
                            <td class="text-right">
                                <span class="app-text text-sm font-bold block">{{ number_format($showtime->price,0,',','.') }}đ</span>
                                @if($showtime->vip_price)
                                    <span class="text-xs text-brand-start font-extrabold">{{ number_format($showtime->vip_price,0,',','.') }}đ</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($showtime->status == 'active')
                                    <span class="status-badge text-success bg-success/10">Đang chiếu</span>
                                @elseif($showtime->status == 'cancelled')
                                    <span class="status-badge text-error bg-error/10">Đã hủy</span>
                                @else
                                    <span class="status-badge text-warning bg-warning/10">Đã chiếu xong</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.showtimes.edit', $showtime) }}" class="inline-flex items-center justify-center w-9 h-9 rounded-xl border app-border app-muted hover:text-brand-start hover:border-brand-start transition-colors" title="Chỉnh sửa">
                                        <i class="ph-bold ph-pencil-simple text-xs"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.showtimes.destroy', $showtime) }}" onsubmit="return confirm('Bạn có chắc muốn xóa suất chiếu này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-xl border app-border app-muted hover:text-white hover:bg-error hover:border-error transition-colors" title="Xóa">
                                            <i class="ph-bold ph-trash text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center app-muted py-10">Không có suất chiếu nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-5 border-t app-border">
            {{ $showtimes->links() }}
        </div>
    </div>
</div>
@endsection
