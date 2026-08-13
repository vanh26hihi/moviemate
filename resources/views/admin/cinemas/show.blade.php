@extends('layouts.admin')

@section('title', $cinema->name.' - MovieMate')
@section('page-title', 'Chi tiết chi nhánh')

@section('content')
@php($header = $branch360['header'])
@php($queue = $branch360['actionQueue'])
<div class="admin-page-header">
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-brand-start">Branch 360 · {{ $header['code'] }}</p>
        <h1 class="admin-page-title">{{ $header['name'] }}</h1>
        <p class="admin-page-subtitle">{{ $header['shortAddress'] }}</p>
        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm app-muted">
            <span><span class="font-semibold app-text">Giờ chi nhánh:</span> {{ $header['localTime']->format('H:i, d/m/Y') }}</span>
            <span><span class="font-semibold app-text">Trạng thái:</span> {{ $header['branchStatus']['label'] }}</span>
            <span><span class="font-semibold app-text">Hôm nay:</span> {{ $header['operatingHours']['label'] }}@if($header['operatingHours']['detail']) · {{ $header['operatingHours']['detail'] }}@endif</span>
        </div>
        <p class="mt-2 text-xs app-muted">Cập nhật lúc {{ $header['generatedAt']->format('H:i:s, d/m/Y') }} · {{ $header['timezone'] }}</p>
    </div>
    @can('cinemas.manage')<a href="{{ route('admin.cinemas.edit', $cinema) }}" class="admin-btn-primary">Chỉnh sửa</a>@endcan
</div>

<section class="app-card mb-6 rounded-2xl border app-border p-6" aria-labelledby="branch-action-queue-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="branch-action-queue-title" class="text-lg font-bold app-text">Cần xử lý</h2>
            <p class="mt-1 text-sm app-muted">{{ $queue['total'] }} việc ưu tiên tại chi nhánh</p>
        </div>
        @if($queue['remaining'] > 0)<span class="text-sm font-semibold app-muted">Còn {{ $queue['remaining'] }} việc chưa hiển thị</span>@endif
    </div>
    @if($queue['total'] === 0)
        <p class="mt-5 rounded-xl border app-border p-4 app-muted">Hiện không có việc khẩn cấp cần xử lý.</p>
    @else
        <div class="mt-5 divide-y app-border">
            @foreach($queue['items'] as $task)
                <article class="grid gap-3 py-4 md:grid-cols-[8rem_1fr_auto] md:items-center">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-brand-start">{{ $task['priority'] }}</div>
                        <div class="mt-1 text-xs app-muted">{{ $task['relevantAt']->format('H:i d/m/Y') }}</div>
                    </div>
                    <p class="text-sm font-medium app-text">{{ $task['message'] }}</p>
                    <a href="{{ $task['actionUrl'] }}" class="admin-btn-secondary">{{ $task['actionLabel'] }}</a>
                </article>
            @endforeach
        </div>
    @endif
</section>

@php($today = $branch360['todayOperations'])
@php($playingNow = $branch360['playingNow'])
@php($upcomingSoon = $branch360['upcomingSoon'])
@php($roomOperations = $branch360['roomOperations'])
<section class="app-card mb-6 rounded-2xl border app-border p-6" aria-labelledby="today-operations-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="today-operations-title" class="text-lg font-bold app-text">Vận hành hôm nay</h2>
        <p class="text-sm app-muted">Ngày vận hành: {{ Carbon\CarbonImmutable::parse($today['businessDate'])->format('d/m/Y') }}</p>
    </div>
    @if(array_sum($today['counts']) === 0)
        <p class="mt-4 app-muted">Hôm nay chưa có lịch chiếu.</p>
    @else
        <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach(['playing' => 'Đang chiếu', 'upcoming' => 'Sắp chiếu', 'completed' => 'Đã hoàn tất', 'cancelled' => 'Đã hủy'] as $key => $label)
                <div class="rounded-xl border app-border p-3"><dt class="text-xs uppercase app-muted">{{ $label }}</dt><dd class="mt-1 text-xl font-bold app-text">{{ $today['counts'][$key] }}</dd></div>
            @endforeach
        </dl>
    @endif
</section>

<div class="mb-6 grid gap-6 lg:grid-cols-2">
    <section class="app-card rounded-2xl border app-border p-6" aria-labelledby="playing-now-title">
        <div class="flex items-center justify-between gap-3"><h2 id="playing-now-title" class="text-lg font-bold app-text">Đang diễn ra</h2><span class="text-sm app-muted">{{ $playingNow['total'] }} suất</span></div>
        <p class="mt-1 text-xs app-muted">Trạng thái vật lý tại thời điểm hiện tại, có thể gồm suất bắt đầu từ ngày vận hành trước.</p>
        <div class="mt-4 divide-y app-border">
            @forelse($playingNow['items'] as $showtime)
                <article class="py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2"><h3 class="font-semibold app-text">{{ $showtime['movieTitle'] }}</h3><span class="text-sm font-semibold text-brand-start">{{ $showtime['formatName'] }}</span></div>
                    <p class="mt-1 text-sm app-muted">Phòng {{ $showtime['roomCode'] }} · {{ $showtime['roomName'] }} · {{ $showtime['startsAt']->format('H:i') }}–{{ $showtime['endsAt']->format('H:i') }}</p>
                </article>
            @empty
                <p class="py-3 app-muted">Hiện không có suất đang chiếu.</p>
            @endforelse
        </div>
    </section>

    <section class="app-card rounded-2xl border app-border p-6" aria-labelledby="upcoming-soon-title">
        <div class="flex items-center justify-between gap-3"><h2 id="upcoming-soon-title" class="text-lg font-bold app-text">Sắp tới 120 phút</h2><span class="text-sm app-muted">Đến {{ $upcomingSoon['untilAt']->format('H:i') }}</span></div>
        <div class="mt-4 divide-y app-border">
            @forelse($upcomingSoon['items'] as $showtime)
                <article class="flex flex-wrap items-start justify-between gap-3 py-3">
                    <div><h3 class="font-semibold app-text">{{ $showtime['movieTitle'] }}</h3><p class="mt-1 text-sm app-muted">{{ $showtime['formatName'] }} · Phòng {{ $showtime['roomCode'] }} · {{ $showtime['roomName'] }}</p></div>
                    <time class="font-semibold text-brand-start">{{ $showtime['startsAt']->format('H:i') }}</time>
                </article>
            @empty
                <p class="py-3 app-muted">Không có suất bắt đầu trong 120 phút tới.</p>
            @endforelse
        </div>
    </section>
</div>

<section class="app-card mb-6 rounded-2xl border app-border p-6" aria-labelledby="room-operations-title">
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="room-operations-title" class="text-lg font-bold app-text">Vận hành phòng</h2>@can('rooms.create')<a href="{{ route('admin.rooms.create') }}" class="admin-btn-secondary">Thêm phòng</a>@endcan</div>
    <div class="mt-4 grid gap-3 lg:grid-cols-2">
        @forelse($roomOperations as $room)
            <article class="rounded-xl border app-border p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>@if($room['roomUrl'])<a href="{{ $room['roomUrl'] }}" class="font-bold app-text hover:text-brand-start">{{ $room['code'] }} · {{ $room['name'] }}</a>@else<span class="font-bold app-text">{{ $room['code'] }} · {{ $room['name'] }}</span>@endif<p class="mt-1 text-xs app-muted">Loại phòng: {{ $room['roomType'] }}</p></div>
                    <span class="text-xs font-bold uppercase tracking-wide text-brand-start">{{ $room['operationalStateLabel'] }}</span>
                </div>
                @if($room['currentShowtime'])
                    <p class="mt-3 text-sm app-text"><span class="font-semibold">Hiện tại:</span> {{ $room['currentShowtime']['formatName'] }} · {{ $room['currentShowtime']['movieTitle'] }} · {{ $room['currentShowtime']['startsAt']->format('H:i') }}–{{ $room['currentShowtime']['endsAt']->format('H:i') }}</p>
                @elseif($room['cleaningReadyAt'])
                    <p class="mt-3 text-sm app-text">Đang vệ sinh · Sẵn sàng lúc {{ $room['cleaningReadyAt']->format('H:i') }}</p>
                @endif
                @if($room['nextShowtime'])
                    <p class="mt-2 text-sm app-muted"><span class="font-semibold app-text">Tiếp theo:</span> {{ $room['nextShowtime']['formatName'] }} · {{ $room['nextShowtime']['movieTitle'] }} · {{ $room['nextShowtime']['startsAt']->format($room['nextShowtime']['startsAt']->toDateString() === $today['businessDate'] ? 'H:i' : 'd/m/Y H:i') }}</p>
                @elseif($room['persistedStatus'] === 'active')
                    <p class="mt-2 text-sm app-muted">Chưa có suất tiếp theo.</p>
                @endif
                @if($room['openIncidentCount'] || $room['layoutWarning'] || $room['futureShowDrift'])
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        @if($room['openIncidentCount'])@if($room['incidentUrl'])<a href="{{ $room['incidentUrl'] }}" class="rounded-full border app-border px-2.5 py-1 text-warning">⚠ {{ $room['openIncidentCount'] }} sự cố đang mở</a>@else<span class="rounded-full border app-border px-2.5 py-1 text-warning">⚠ {{ $room['openIncidentCount'] }} sự cố đang mở</span>@endif @endif
                        @if($room['layoutWarning'])@if($room['layoutUrl'])<a href="{{ $room['layoutUrl'] }}" class="rounded-full border app-border px-2.5 py-1 text-warning">⚠ Chưa có layout đã xuất bản</a>@else<span class="rounded-full border app-border px-2.5 py-1 text-warning">⚠ Chưa có layout đã xuất bản</span>@endif @endif
                        @if($room['futureShowDrift'])<span class="rounded-full border app-border px-2.5 py-1 text-warning">⚠ Phòng ngừng hoạt động còn suất tương lai</span>@endif
                    </div>
                @endif
            </article>
        @empty
            <p class="app-muted">Chi nhánh chưa có phòng chiếu.</p>
        @endforelse
    </div>
</section>

@php($counterOperations = $branch360['counterOperations'])
<section class="app-card mb-6 rounded-2xl border app-border p-6" aria-labelledby="counter-operations-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="counter-operations-title" class="text-lg font-bold app-text">Vận hành quầy</h2>
            <p class="mt-1 text-sm app-muted">{{ $counterOperations['firstPrintBookingCount'] }} đơn sắp tới còn vé chưa in · {{ $counterOperations['unprintedTicketCount'] }} vé vật lý chưa in</p>
        </div>
        @if($counterOperations['overflowCount'] > 0)<span class="text-sm font-semibold app-muted">Còn {{ $counterOperations['overflowCount'] }} đơn cần xử lý</span>@endif
    </div>
    @if($counterOperations['replacementPrintPendingCount'] > 0)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border app-border p-3 text-sm">
            <span class="font-semibold text-warning">{{ $counterOperations['replacementPrintPendingCount'] }} yêu cầu in vé thay thế cần xử lý</span>
            @if($counterOperations['replacementPrintActionUrl'])<a href="{{ $counterOperations['replacementPrintActionUrl'] }}" class="admin-btn-secondary">Xem việc cần xử lý</a>@endif
        </div>
    @endif
    <div class="mt-4 divide-y app-border">
        @forelse($counterOperations['items'] as $item)
            <article class="grid gap-3 py-4 sm:grid-cols-[6rem_1fr_auto] sm:items-center">
                <div><time class="font-bold text-brand-start">{{ $item['startsAt']->format('H:i') }}</time><p class="mt-1 text-xs app-muted">{{ $item['startsAt']->format('d/m/Y') }}</p></div>
                <div>
                    <h3 class="font-semibold app-text">{{ $item['bookingCode'] }} · {{ $item['movieTitle'] }}</h3>
                    <p class="mt-1 text-sm app-muted">{{ $item['presentationFormat'] }} · Phòng {{ $item['roomCode'] }} · {{ $item['roomName'] }}</p>
                    <p class="mt-1 text-sm font-medium app-text">Còn {{ $item['unprintedTicketCount'] }}/{{ $item['totalTicketCount'] }} vé chưa in</p>
                </div>
                @if($item['actionUrl'])<a href="{{ $item['actionUrl'] }}" class="admin-btn-secondary">{{ $item['actionLabel'] }}</a>@endif
            </article>
        @empty
            <p class="py-3 app-muted">Không có đơn sắp tới cần in vé lần đầu.</p>
        @endforelse
    </div>
</section>

@isset($branch360['finance'])
@php($finance = $branch360['finance'])
<section class="app-card mb-6 rounded-2xl border app-border p-6" aria-labelledby="branch-finance-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="branch-finance-title" class="text-lg font-bold app-text">Tài chính hôm nay</h2>
            <p class="mt-1 text-sm app-muted">Theo ngày xác minh/thu tại chi nhánh {{ $finance['generatedAt']->format('d/m/Y') }}</p>
        </div>
        <a href="{{ $finance['reportUrl'] }}" class="admin-btn-secondary">Xem báo cáo chi nhánh</a>
    </div>
    <dl class="mt-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border app-border p-4">
            <dt class="text-sm app-muted">Tiền đã xác minh/thu hôm nay</dt>
            <dd class="mt-2 text-3xl font-extrabold text-brand-start">{{ number_format($finance['collectedAmount'], 0, ',', '.') }} ₫</dd>
        </div>
        <div class="rounded-xl border app-border p-4">
            <dt class="text-sm app-muted">Đơn thanh toán hợp lệ hôm nay</dt>
            <dd class="mt-2 text-3xl font-extrabold app-text">{{ number_format($finance['paidBookingCount'], 0, ',', '.') }} đơn</dd>
        </div>
    </dl>
</section>
@endisset

<div class="grid gap-6 lg:grid-cols-[1fr_1.5fr]">
    <section class="app-card rounded-2xl border app-border p-6 space-y-4">
        <div class="border-b app-border pb-3"><div class="text-xs uppercase app-muted">Quận / huyện</div><div class="mt-1 font-semibold app-text">{{ $cinema->district ?: '—' }}</div></div>
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
