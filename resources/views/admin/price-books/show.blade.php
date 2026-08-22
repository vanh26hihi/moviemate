@extends('layouts.admin')
@section('title', 'Phiên bản bảng giá v'.$version->version_number)
@section('page-title', 'Phiên bản bảng giá v'.$version->version_number)
@section('content')
@php
    $statusLabel = ['draft'=>'Bản nháp','published'=>'Đã phát hành','retired'=>'Đã ngừng sử dụng'][$version->status];
    $dimensionLabels = ['seat_type'=>'Loại ghế','room_type'=>'Loại phòng','time_window'=>'Khung giờ','weekend'=>'Cuối tuần','holiday'=>'Ngày lễ','cinema'=>'Chi nhánh','room'=>'Phòng'];
    $weekdayLabels = [1=>'Thứ 2',2=>'Thứ 3',3=>'Thứ 4',4=>'Thứ 5',5=>'Thứ 6',6=>'Thứ 7',7=>'Chủ nhật'];
@endphp
<div class="admin-page-header">
    <div>
        <a class="text-sm font-bold text-brand-start" href="{{ route('admin.price-books.index') }}">← Tất cả phiên bản</a>
        <h1 class="admin-page-title sr-only mt-2 sm:not-sr-only">Phiên bản bảng giá v{{ $version->version_number }}</h1>
        <p class="admin-page-subtitle">{{ $priceBook->name }} · <span class="status-badge border app-border">{{ $statusLabel }}</span></p>
    </div>
</div>
<x-validation-summary class="mb-5" :errors="$errors"/>

@if($version->status === 'draft')
    <div class="mb-5 rounded-xl border border-brand-start/30 bg-brand-start/10 p-4 text-sm">Sao chép độc lập từ phiên bản đã phát hành tạo ID mới và bộ điều chỉnh độc lập. Hãy xác nhận thời gian hiệu lực trước khi phát hành.</div>
@elseif($version->status === 'published')
    <div class="mb-5 rounded-xl border app-border p-4 text-sm">Định nghĩa tài chính đã phát hành là bất biến. Các suất chiếu đã lưu snapshot giá không bị thay đổi khi phiên bản này ngừng sử dụng.</div>
@else
    <div class="mb-5 rounded-xl border app-border p-4 text-sm">Phiên bản đã ngừng sử dụng và chỉ còn để đọc. Snapshot giá của các suất chiếu trước đây vẫn giữ nguyên.</div>
@endif

<div class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
    <section class="cinema-card p-5 sm:p-6" aria-labelledby="overview-title">
        <h2 id="overview-title" class="text-xl font-extrabold app-heading">Tổng quan</h2>
        @if($canManagePriceBook && $version->status === 'draft')
            <form class="mt-5 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('admin.price-books.versions.update', $version) }}">
                @csrf @method('PATCH')
                <div class="sm:col-span-2"><label class="admin-label" for="base-price">Giá cơ sở toàn chuỗi</label><input class="admin-input" id="base-price" type="number" min="1" name="base_price_vnd" required value="{{ old('base_price_vnd', $version->base_price_vnd) }}"><p class="mt-1 text-xs app-muted">Phiên bản có đúng một giá cơ sở VND; mọi phạm vi còn lại là điều chỉnh.</p></div>
                <div><label class="admin-label" for="effective-from">Áp dụng từ</label><input class="admin-input" id="effective-from" type="date" name="effective_from" required value="{{ old('effective_from', $version->effective_from?->format('Y-m-d')) }}"></div>
                <div><label class="admin-label" for="effective-until">Đến trước ngày</label><input class="admin-input" id="effective-until" type="date" name="effective_until" value="{{ old('effective_until', $version->effective_until?->format('Y-m-d')) }}"><p class="mt-1 text-xs app-muted">Để trống: không giới hạn ngày kết thúc.</p></div>
                <div class="sm:col-span-2"><button class="admin-btn-primary">Lưu bản nháp</button></div>
            </form>
        @else
            <dl class="mt-5 space-y-4">
                <div><dt class="app-muted">Giá cơ sở toàn chuỗi</dt><dd class="text-2xl font-black tabular-nums">{{ number_format((int) $version->base_price_vnd, 0, ',', '.') }} ₫</dd></div>
                <div><dt class="app-muted">Áp dụng từ</dt><dd class="font-bold">{{ $version->effective_from?->format('d/m/Y') ?? 'Chưa đặt' }}</dd></div>
                <div><dt class="app-muted">Đến trước ngày</dt><dd class="font-bold">{{ $version->effective_until?->format('d/m/Y') ?? 'Không giới hạn ngày kết thúc' }}</dd></div>
            </dl>
        @endif

        @if($canManagePriceBook && $version->status === 'published')
            <form class="mt-6 rounded-2xl border app-border p-4" method="POST" action="{{ route('admin.price-books.versions.copy', $version) }}">
                @csrf
                <h3 class="font-extrabold app-heading">Sao chép thành bản nháp mới</h3>
                <p class="mt-1 text-xs app-muted">Không tự động giữ thời gian hiệu lực để tránh vô tình tạo khoảng chồng lấn.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2"><div><label class="admin-label" for="copy-from">Áp dụng từ</label><input class="admin-input" id="copy-from" type="date" name="effective_from"></div><div><label class="admin-label" for="copy-until">Đến trước ngày</label><input class="admin-input" id="copy-until" type="date" name="effective_until"></div></div>
                <button class="admin-btn-secondary mt-3">Sao chép thành bản nháp mới</button>
            </form>
        @endif
    </section>

    <section class="cinema-card overflow-hidden" aria-labelledby="adjustments-title">
        <div class="border-b app-border p-5 sm:p-6"><h2 id="adjustments-title" class="text-xl font-extrabold app-heading">Điều chỉnh giá</h2><p class="mt-1 text-sm app-muted">Giá trị dương hoặc âm theo VND; ngày lễ thay thế cuối tuần khi cùng áp dụng.</p></div>
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[680px]">
                <thead><tr><th>Loại</th><th>Phạm vi</th><th>Nhãn</th><th class="text-right">Điều chỉnh</th>@if($canManagePriceBook && $version->status === 'draft')<th></th>@endif</tr></thead>
                <tbody>
                @forelse($version->adjustments as $adjustment)
                    @php
                        $target = match($adjustment->dimension) {
                            'seat_type' => $adjustment->seatType?->name,
                            'room_type' => $adjustment->roomType?->name,
                            'cinema' => $adjustment->cinema?->name,
                            'room' => ($adjustment->room?->code ? $adjustment->room->code.' — ' : '').$adjustment->room?->name,
                            'time_window' => substr($adjustment->time_start,0,5).' → '.substr($adjustment->time_end,0,5).' · theo giờ bắt đầu suất chiếu',
                            'weekend' => collect($adjustment->weekend_days)->map(fn($day) => $weekdayLabels[$day] ?? $day)->join(', '),
                            'holiday' => $adjustment->holiday_date_from?->format('d/m/Y').' → trước '.$adjustment->holiday_date_until?->format('d/m/Y'),
                        };
                    @endphp
                    <tr><td class="font-bold">{{ $dimensionLabels[$adjustment->dimension] }}</td><td>{{ $target }}</td><td>{{ $adjustment->label }}@if($adjustment->dimension === 'seat_type' && $adjustment->seatType?->is_pair)<span class="block text-xs app-muted">Tính một lần cho một cặp ghế đôi.</span>@endif</td><td class="text-right font-black tabular-nums">{{ $adjustment->amount_vnd > 0 ? '+' : '−' }}{{ number_format(abs($adjustment->amount_vnd), 0, ',', '.') }} ₫</td>
                    @if($canManagePriceBook && $version->status === 'draft')<td class="text-right"><details><summary class="cursor-pointer font-bold text-brand-start">Chỉnh sửa</summary><div class="mt-3 min-w-72 text-left"><form method="POST" action="{{ route('admin.price-books.versions.adjustments.update', [$version,$adjustment]) }}" class="space-y-2">@csrf @method('PATCH')<input type="hidden" name="dimension" value="{{ $adjustment->dimension }}"><input class="admin-input" name="label" value="{{ $adjustment->label }}" aria-label="Nhãn điều chỉnh" required><input class="admin-input" type="number" name="amount_vnd" value="{{ $adjustment->amount_vnd }}" aria-label="Số tiền điều chỉnh" required>@if($adjustment->seat_type_id)<input type="hidden" name="seat_type_id" value="{{ $adjustment->seat_type_id }}">@elseif($adjustment->room_type_id)<input type="hidden" name="room_type_id" value="{{ $adjustment->room_type_id }}">@elseif($adjustment->cinema_id)<input type="hidden" name="cinema_id" value="{{ $adjustment->cinema_id }}">@elseif($adjustment->room_id)<input type="hidden" name="room_id" value="{{ $adjustment->room_id }}">@elseif($adjustment->dimension === 'time_window')<input type="hidden" name="time_start" value="{{ substr($adjustment->time_start,0,5) }}"><input type="hidden" name="time_end" value="{{ substr($adjustment->time_end,0,5) }}">@elseif($adjustment->dimension === 'holiday')<input type="hidden" name="holiday_date_from" value="{{ $adjustment->holiday_date_from?->format('Y-m-d') }}"><input type="hidden" name="holiday_date_until" value="{{ $adjustment->holiday_date_until?->format('Y-m-d') }}">@elseif($adjustment->dimension === 'weekend')@foreach($adjustment->weekend_days as $day)<input type="hidden" name="weekend_days[]" value="{{ $day }}">@endforeach @endif<button class="admin-btn-secondary">Lưu điều chỉnh</button></form><form method="POST" action="{{ route('admin.price-books.versions.adjustments.destroy', [$version,$adjustment]) }}" class="mt-2">@csrf @method('DELETE')<button class="text-sm font-bold text-error">Xóa khỏi bản nháp</button></form></div></details></td>@endif</tr>
                @empty<tr><td colspan="5" class="py-8 text-center app-muted">Chưa có điều chỉnh giá.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@if($canManagePriceBook && $version->status === 'draft')
<section class="cinema-card mt-6 p-5 sm:p-6" aria-labelledby="add-adjustment-title">
    <h2 id="add-adjustment-title" class="text-xl font-extrabold app-heading">Thêm điều chỉnh giá</h2>
    <form id="adjustment-form" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4" method="POST" action="{{ route('admin.price-books.versions.adjustments.store', $version) }}">@csrf
        <div><label class="admin-label" for="adjustment-dimension">Loại điều chỉnh</label><select class="admin-input" id="adjustment-dimension" name="dimension" required>@foreach($dimensionLabels as $value=>$label)<option value="{{ $value }}" @selected(old('dimension')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="admin-label" for="adjustment-label">Nhãn giải thích</label><input class="admin-input" id="adjustment-label" name="label" maxlength="255" required value="{{ old('label') }}"></div>
        <div><label class="admin-label" for="adjustment-amount">Điều chỉnh VND</label><input class="admin-input" id="adjustment-amount" type="number" name="amount_vnd" required value="{{ old('amount_vnd') }}"><p class="mt-1 text-xs app-muted">Nhập số dương để tăng, số âm để giảm.</p></div>
        <div data-adjustment-field="seat_type"><label class="admin-label" for="adjustment-seat-type">Loại ghế</label><select class="admin-input" id="adjustment-seat-type" name="seat_type_id">@foreach($seatTypes as $seatType)<option value="{{ $seatType->id }}">{{ $seatType->name }}{{ $seatType->is_pair ? ' · tính một lần/cặp' : '' }}</option>@endforeach</select></div>
        <div data-adjustment-field="room_type"><label class="admin-label" for="adjustment-room-type">Loại phòng</label><select class="admin-input" id="adjustment-room-type" name="room_type_id">@foreach($roomTypes as $roomType)<option value="{{ $roomType->id }}">{{ $roomType->name }}</option>@endforeach</select></div>
        <div data-adjustment-field="cinema"><label class="admin-label" for="adjustment-cinema">Chi nhánh</label><select class="admin-input" id="adjustment-cinema" name="cinema_id">@foreach($previewCinemas as $cinema)<option value="{{ $cinema->id }}">{{ $cinema->name }}</option>@endforeach</select></div>
        <div data-adjustment-field="room"><label class="admin-label" for="adjustment-room">Phòng</label><select class="admin-input" id="adjustment-room" name="room_id">@foreach($previewRooms->groupBy('cinema_id') as $cinemaId=>$rooms)<optgroup label="{{ $rooms->first()->cinema?->name }}">@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>@endforeach</optgroup>@endforeach</select></div>
        <div data-adjustment-field="time_window"><label class="admin-label" for="adjustment-time-start">Giờ bắt đầu</label><input class="admin-input" id="adjustment-time-start" type="time" name="time_start"><p class="mt-1 text-xs app-muted">Theo giờ bắt đầu suất chiếu; hỗ trợ 22:00 → 02:00.</p></div>
        <div data-adjustment-field="time_window"><label class="admin-label" for="adjustment-time-end">Giờ kết thúc</label><input class="admin-input" id="adjustment-time-end" type="time" name="time_end"></div>
        <fieldset data-adjustment-field="weekend" class="md:col-span-2"><legend class="admin-label">Ngày cuối tuần</legend><div class="flex flex-wrap gap-3">@foreach($weekdayLabels as $day=>$label)<label class="flex items-center gap-2"><input type="checkbox" name="weekend_days[]" value="{{ $day }}">{{ $label }}</label>@endforeach</div></fieldset>
        <div data-adjustment-field="holiday"><label class="admin-label" for="holiday-from">Ngày bắt đầu</label><input class="admin-input" id="holiday-from" type="date" name="holiday_date_from"><p class="mt-1 text-xs app-muted">Ngày lễ khớp sẽ thay thế điều chỉnh cuối tuần.</p></div>
        <div data-adjustment-field="holiday"><label class="admin-label" for="holiday-until">Đến trước ngày</label><input class="admin-input" id="holiday-until" type="date" name="holiday_date_until"></div>
        <div class="md:col-span-2 xl:col-span-4"><button class="admin-btn-primary">Thêm vào bản nháp</button></div>
    </form>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dimension = document.getElementById('adjustment-dimension');
    const sync = () => document.querySelectorAll('[data-adjustment-field]').forEach((container) => {
        const active = container.dataset.adjustmentField === dimension.value;
        container.hidden = !active;
        container.querySelectorAll('input,select').forEach((input) => input.disabled = !active);
    });
    dimension.addEventListener('change', sync); sync();
});
</script>
@endif

<div class="mt-6">@include('admin.price-books._preview')</div>

@if($canManagePriceBook && $version->status === 'draft')
<form class="mt-6 rounded-2xl border border-warning/30 bg-warning/10 p-5" method="POST" action="{{ route('admin.price-books.versions.publish', $version) }}">@csrf<h2 class="font-extrabold app-heading">Phát hành phiên bản</h2><p class="mt-1 text-sm">Sau khi phát hành, nội dung tài chính của phiên bản không thể chỉnh sửa.</p><button class="admin-btn-primary mt-4" onclick="return confirm('Phát hành và khóa định nghĩa tài chính của phiên bản này?')">Phát hành phiên bản</button></form>
@elseif($canManagePriceBook && $version->status === 'published')
<form class="mt-6 rounded-2xl border app-border p-5" method="POST" action="{{ route('admin.price-books.versions.retire', $version) }}">@csrf<h2 class="font-extrabold app-heading">Ngừng sử dụng phiên bản</h2><p class="mt-1 text-sm app-muted">Các suất chiếu đã lưu snapshot giá từ phiên bản này vẫn giữ nguyên giá.</p><button class="admin-btn-secondary mt-4" onclick="return confirm('Ngừng dùng phiên bản cho các lần phân giải giá trong tương lai?')">Ngừng sử dụng</button></form>
@endif
@endsection
