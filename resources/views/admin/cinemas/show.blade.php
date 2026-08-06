@extends('layouts.admin')

@section('title', $cinema->name.' - MovieMate')
@section('page-title', 'Chi tiết chi nhánh')

@section('content')
<div class="admin-page-header"><div><p class="text-xs font-bold uppercase tracking-wider text-brand-start">{{ $cinema->code }}</p><h1 class="admin-page-title">{{ $cinema->name }}</h1><p class="admin-page-subtitle">{{ $cinema->address }}</p></div>@can('cinemas.manage')<a href="{{ route('admin.cinemas.edit', $cinema) }}" class="admin-btn-primary">Chỉnh sửa</a>@endcan</div>
<div class="grid gap-6 lg:grid-cols-[1fr_1.5fr]">
    <section class="app-card rounded-2xl border app-border p-6 space-y-4">
        <h2 class="text-lg font-bold app-text">Thông tin chi nhánh</h2>
        @foreach(['Tỉnh / thành phố' => $cinema->city, 'Điện thoại' => $cinema->phone, 'Múi giờ' => $cinema->timezone, 'Trạng thái' => ($cinema->status === 'active' ? 'Đang hoạt động' : 'Ngừng hoạt động'), 'Phòng đang mở' => $cinema->active_rooms_count, 'Suất chiếu sắp tới' => $cinema->active_showtimes_count, 'Đơn lịch sử' => $cinema->bookings_count] as $label => $value)<div class="border-b app-border pb-3"><div class="text-xs uppercase app-muted">{{ $label }}</div><div class="mt-1 font-semibold app-text">{{ $value ?: '—' }}</div></div>@endforeach
    </section>
    <div class="space-y-6">
        <section class="app-card rounded-2xl border app-border p-6"><div class="flex items-center justify-between"><h2 class="text-lg font-bold app-text">Phòng chiếu</h2>@can('rooms.create')<a href="{{ route('admin.rooms.create') }}" class="admin-btn-secondary">Thêm phòng</a>@endcan</div><div class="mt-4 divide-y app-border">@forelse($cinema->rooms as $room)<a href="{{ route('admin.rooms.show', $room) }}" class="flex justify-between py-3"><span class="font-semibold app-text">{{ $room->code }} · {{ $room->name }}</span><span class="text-sm app-muted">{{ $room->showtimes_count }} suất</span></a>@empty<p class="app-muted">Chưa có phòng chiếu.</p>@endforelse</div></section>
        <section class="app-card rounded-2xl border app-border p-6"><h2 class="text-lg font-bold app-text">Manager và Staff</h2><div class="mt-4 space-y-3">@forelse($cinema->activeAssignments as $assignment)<div class="flex justify-between"><span class="app-text">{{ $assignment->user->name }}</span><span class="text-xs font-bold uppercase text-brand-start">{{ $assignment->user->role?->display_name }}</span></div>@empty<p class="app-muted">Chưa phân công nhân sự.</p>@endforelse</div></section>
    </div>
</div>
@can('cinemas.operations.manage')
<section class="app-card mt-6 rounded-2xl border app-border p-6">
    <h2 class="text-lg font-bold app-text">Giờ hoạt động</h2>
    <p class="mt-1 text-sm app-muted">Giờ mở cửa là thời điểm bắt đầu sớm nhất; nhận suất chiếu cuối giới hạn giờ bắt đầu, phim vẫn có thể kết thúc sau nửa đêm.</p>
    <form method="POST" action="{{ route('admin.cinemas.operating-hours.update', $cinema) }}" class="mt-5 space-y-4">@csrf @method('PATCH')
        @foreach([1=>'Thứ hai',2=>'Thứ ba',3=>'Thứ tư',4=>'Thứ năm',5=>'Thứ sáu',6=>'Thứ bảy',7=>'Chủ nhật'] as $day => $label)
            @php($hour = $cinema->operatingHours->firstWhere('day_of_week', $day))
            <div class="grid items-end gap-3 rounded-xl border app-border p-3 md:grid-cols-4">
                <div class="font-bold app-text">{{ $label }}<input type="hidden" name="hours[{{ $day }}][day_of_week]" value="{{ $day }}"></div>
                <label class="text-sm app-muted">Mở cửa<input type="time" name="hours[{{ $day }}][opens_at]" value="{{ old("hours.$day.opens_at", $hour?->opens_at ? substr($hour->opens_at,0,5) : '08:00') }}" class="cinema-input mt-1"></label>
                <label class="text-sm app-muted">Nhận suất chiếu cuối<input type="time" name="hours[{{ $day }}][latest_show_start_at]" value="{{ old("hours.$day.latest_show_start_at", $hour?->latest_show_start_at ? substr($hour->latest_show_start_at,0,5) : '23:00') }}" class="cinema-input mt-1"></label>
                <label class="pb-3"><input type="checkbox" name="hours[{{ $day }}][is_closed]" value="1" @checked(old("hours.$day.is_closed", $hour?->is_closed))> Đóng cửa cả ngày</label>
            </div>
        @endforeach
        <div class="max-w-sm"><label class="cinema-label">Vệ sinh phòng mặc định (phút)</label><input type="number" min="0" max="180" name="default_cleaning_buffer_minutes" value="{{ old('default_cleaning_buffer_minutes', $cinema->default_cleaning_buffer_minutes ?? 15) }}" class="cinema-input" required></div>
        <button class="admin-btn-primary">Cập nhật giờ hoạt động</button>
    </form>
</section>
@endcan
@endsection
