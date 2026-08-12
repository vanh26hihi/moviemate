@extends('layouts.admin')

@section('title', 'Tạo nhiều suất chiếu - Quản trị MovieMate')
@section('page-title', 'Tạo nhiều suất chiếu')

@section('content')
<div
    class="space-y-6"
    data-bulk-showtime-workspace
    data-preview-endpoint="{{ route('admin.showtimes.bulk.preview') }}"
    data-publish-endpoint="{{ route('admin.showtimes.bulk.store') }}"
    data-initial-message="{{ $copyMessage }}"
>
    <script type="application/json" data-bulk-initial-rows>@json($initialRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">Lịch chiếu</p>
            <h1 class="text-3xl font-extrabold app-text">Tạo nhiều suất chiếu</h1>
            <p class="app-muted mt-2">Nhập nhiều phòng hoặc nhiều ngày trong cùng một chi nhánh. Kiểm tra không giữ chỗ; hệ thống kiểm tra lại toàn bộ khi đăng.</p>
            @if($cinema)
                <p class="text-sm app-text mt-2">Chi nhánh: <strong>{{ $cinema->name }}</strong> · {{ $cinema->timezone }}</p>
            @else
                <p class="text-sm app-muted mt-2">Quản trị toàn hệ thống: mỗi lô vẫn chỉ được chứa phòng của một chi nhánh.</p>
            @endif
        </div>
        <a href="{{ route('admin.showtimes.index') }}" class="btn-secondary"><i class="ph ph-arrow-left"></i> Quay lại lịch chiếu</a>
    </div>

    <form data-bulk-showtime-form class="space-y-6">
        @csrf
        <div class="cinema-card overflow-hidden">
            <div class="p-5 border-b app-border flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-extrabold app-text">Danh sách suất dự kiến</h2>
                    <p class="text-sm app-muted mt-1">Phim, phòng, ngày và giờ là ý định đầu vào; mọi giá trị vận hành còn lại do máy chủ xác định.</p>
                </div>
                <button type="button" class="btn-secondary" data-bulk-add-row><i class="ph-bold ph-plus"></i> Thêm dòng</button>
            </div>
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[980px]">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Phim</th>
                            <th>Phòng</th>
                            <th>Ngày</th>
                            <th>Giờ bắt đầu</th>
                            <th>Kết quả vận hành</th>
                            <th class="text-right">Xóa</th>
                        </tr>
                    </thead>
                    <tbody data-bulk-rows></tbody>
                </table>
            </div>
        </div>

        <div class="cinema-card p-5">
            <div class="grid grid-cols-3 gap-4 text-center" data-bulk-summary>
                <div><span class="block text-xs app-muted">Tổng số suất</span><strong class="text-xl app-text" data-summary-total>0</strong></div>
                <div><span class="block text-xs app-muted">Hợp lệ</span><strong class="text-xl text-success" data-summary-valid>0</strong></div>
                <div><span class="block text-xs app-muted">Không hợp lệ</span><strong class="text-xl text-error" data-summary-invalid>0</strong></div>
            </div>
            <p class="mt-4 text-sm app-muted" data-bulk-message aria-live="polite">Thêm hoặc chỉnh sửa dòng, sau đó chọn Kiểm tra lịch.</p>
            <div class="mt-5 flex flex-wrap justify-end gap-3">
                <button type="button" class="btn-secondary" data-bulk-preview><i class="ph ph-magnifying-glass"></i> Kiểm tra lịch</button>
                <button type="submit" class="btn-primary" data-bulk-publish disabled><i class="ph-bold ph-upload-simple"></i> Đăng toàn bộ</button>
            </div>
        </div>
    </form>

    <template data-bulk-row-template>
        <tr data-bulk-row>
            <td class="font-bold app-muted" data-row-number></td>
            <td>
                <select class="cinema-input min-w-[220px]" data-row-movie required>
                    <option value="">Chọn phim</option>
                    @foreach($movies as $movie)
                        <option value="{{ $movie->id }}">{{ $movie->title }} — {{ $movie->duration }} phút{{ in_array($movie->status, \App\Models\Movie::SCHEDULABLE_STATUSES, true) ? '' : ' · không còn khả dụng' }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select class="cinema-input min-w-[220px]" data-row-room required>
                    <option value="">Chọn phòng</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}">{{ $room->cinema->code }} · {{ $room->code }} · {{ $room->name }}{{ $room->status === 'active' && $room->latestPublishedLayout ? '' : ' · không còn khả dụng' }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="date" class="cinema-input min-w-[150px]" data-row-date required></td>
            <td><input type="time" class="cinema-input min-w-[130px]" data-row-time required></td>
            <td class="min-w-[280px]">
                <span class="status-badge app-muted app-secondary" data-row-status>Chưa kiểm tra</span>
                <p class="text-xs app-muted mt-2" data-row-window></p>
                <p class="text-xs text-error mt-1" data-row-error></p>
            </td>
            <td class="text-right"><button type="button" class="inline-flex items-center justify-center w-9 h-9 rounded-xl border app-border app-muted hover:text-error hover:border-error" data-bulk-remove-row aria-label="Xóa dòng"><i class="ph-bold ph-trash"></i></button></td>
        </tr>
    </template>
</div>
@endsection
