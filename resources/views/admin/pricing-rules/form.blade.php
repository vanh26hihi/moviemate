@extends('layouts.admin')
@section('title', ($rule->exists ? 'Sửa' : 'Tạo').' quy tắc giá - MovieMate')
@section('page-title', $rule->exists ? 'Sửa quy tắc giá' : 'Tạo quy tắc giá')

@section('content')
<form method="POST" action="{{ $rule->exists ? route('admin.pricing-rules.update', $rule) : route('admin.pricing-rules.store') }}" class="app-card mx-auto max-w-5xl space-y-6 rounded-2xl border app-border p-6" data-pricing-rule-form>
    @csrf @if($rule->exists) @method('PATCH') @endif

    <section class="space-y-4">
        <div><h2 class="text-lg font-bold app-text">Thông tin quy tắc</h2><p class="text-sm app-muted">Chọn loại quy tắc trước; các điều kiện liên quan sẽ được làm nổi bật bên dưới.</p></div>
        <div><label for="name" class="cinema-label">Tên quy tắc <span aria-hidden="true">*</span></label><input id="name" name="name" value="{{ old('name', $rule->name) }}" class="cinema-input" required maxlength="255">@error('name')<p class="text-error" role="alert">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 md:grid-cols-3">
            <div><label for="rule_type" class="cinema-label">Loại quy tắc</label><select id="rule_type" name="rule_type" class="cinema-input" data-pricing-rule-type>@foreach($pricingRuleTypes as $type => $label)<option value="{{ $type }}" @selected(old('rule_type', $rule->rule_type ?? 'base') === $type)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="cinema_id" class="cinema-label">Chi nhánh áp dụng</label><select id="cinema_id" name="cinema_id" class="cinema-input"><option value="">Toàn hệ thống (chỉ Admin)</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected(old('cinema_id', $rule->cinema_id) == $cinema->id)>{{ $cinema->name }}</option>@endforeach</select>@error('cinema_id')<p class="text-error">{{ $message }}</p>@enderror</div>
            <div data-pricing-dimension="room_adjustment"><label for="room_id" class="cinema-label">Phòng chiếu</label><select id="room_id" name="room_id" class="cinema-input"><option value="">Mọi phòng</option>@foreach($rooms as $room)<option value="{{ $room->id }}" @selected(old('room_id', $rule->room_id) == $room->id)>{{ $room->code }} · {{ $room->name }}</option>@endforeach</select>@error('room_id')<p class="text-error">{{ $message }}</p>@enderror</div>
        </div>
    </section>

    <section class="rounded-2xl border app-border app-card-soft p-5 space-y-4">
        <div><h2 class="text-lg font-bold app-text">Điều kiện áp dụng</h2><p class="text-sm app-muted">Để trống điều kiện phụ nếu quy tắc không cần giới hạn thêm.</p></div>
        <div class="grid gap-4 md:grid-cols-3">
            <div data-pricing-dimension="seat_type"><label for="seat_type" class="cinema-label">Loại ghế</label><select id="seat_type" name="seat_type" class="cinema-input"><option value="">Không giới hạn</option>@foreach($seatTypes as $type => $label)<option value="{{ $type }}" @selected(old('seat_type', $rule->seat_type) === $type)>{{ $label }}</option>@endforeach</select>@error('seat_type')<p class="text-error">{{ $message }}</p>@enderror</div>
            <div data-pricing-dimension="room_type"><label for="pricing_room_type" class="cinema-label">Loại phòng</label><x-admin.room-type-select id="pricing_room_type" :room-types="$roomTypes" :selected="old('room_type', $rule->room_type)" :allow-empty="true" :can-create="auth()->user()->hasPermission('room_types.manage')" />@error('room_type')<p class="text-error">{{ $message }}</p>@enderror</div>
            <div><label for="amount_vnd" class="cinema-label">{{ old('rule_type', $rule->rule_type) === 'base' ? 'Giá vé cơ bản' : 'Mức điều chỉnh' }} (VNĐ)</label><input id="amount_vnd" type="number" name="amount_vnd" value="{{ old('amount_vnd', $rule->amount_vnd ?? 0) }}" class="cinema-input" required><p class="mt-1 text-xs app-muted">Giá cơ bản không được âm. Phụ thu có thể âm nếu là khoản giảm.</p>@error('amount_vnd')<p class="text-error">{{ $message }}</p>@enderror</div>
        </div>
        <div class="grid gap-4 md:grid-cols-4" data-pricing-dimension="time_window holiday">
            <div data-pricing-dimension="holiday"><label for="date_start" class="cinema-label">Từ ngày đặc biệt</label><input id="date_start" type="date" name="date_start" value="{{ old('date_start', $rule->date_start?->format('Y-m-d')) }}" class="cinema-input">@error('date_start')<p class="text-error">{{ $message }}</p>@enderror</div>
            <div data-pricing-dimension="holiday"><label for="date_end" class="cinema-label">Đến ngày</label><input id="date_end" type="date" name="date_end" value="{{ old('date_end', $rule->date_end?->format('Y-m-d')) }}" class="cinema-input">@error('date_end')<p class="text-error">{{ $message }}</p>@enderror</div>
            <div data-pricing-dimension="time_window"><label for="time_start" class="cinema-label">Khung giờ từ</label><input id="time_start" type="time" name="time_start" value="{{ old('time_start', $rule->time_start ? substr($rule->time_start, 0, 5) : '') }}" class="cinema-input">@error('time_start')<p class="text-error">{{ $message }}</p>@enderror</div>
            <div data-pricing-dimension="time_window"><label for="time_end" class="cinema-label">Đến</label><input id="time_end" type="time" name="time_end" value="{{ old('time_end', $rule->time_end ? substr($rule->time_end, 0, 5) : '') }}" class="cinema-input">@error('time_end')<p class="text-error">{{ $message }}</p>@enderror</div>
        </div>
        <fieldset data-pricing-dimension="weekend"><legend class="cinema-label">Ngày trong tuần</legend><div class="flex flex-wrap gap-4">@foreach([1 => 'Thứ hai', 2 => 'Thứ ba', 3 => 'Thứ tư', 4 => 'Thứ năm', 5 => 'Thứ sáu', 6 => 'Thứ bảy', 7 => 'Chủ nhật'] as $day => $label)<label><input type="checkbox" name="days_of_week[]" value="{{ $day }}" @checked(in_array($day, old('days_of_week', $rule->days_of_week ?? [])))> {{ $label }}</label>@endforeach</div></fieldset>
    </section>

    <section class="space-y-4"><h2 class="text-lg font-bold app-text">Hiệu lực và ưu tiên</h2><div class="grid gap-4 md:grid-cols-4"><div><label class="cinema-label" for="priority">Ưu tiên</label><input id="priority" type="number" name="priority" value="{{ old('priority', $rule->priority ?? 0) }}" class="cinema-input"></div><div><label class="cinema-label" for="starts_at">Hiệu lực từ</label><input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $rule->starts_at?->format('Y-m-d\TH:i')) }}" class="cinema-input"></div><div><label class="cinema-label" for="ends_at">Hiệu lực đến</label><input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $rule->ends_at?->format('Y-m-d\TH:i')) }}" class="cinema-input"></div><div><label class="cinema-label" for="status">Trạng thái</label><select id="status" name="status" class="cinema-input"><option value="active" @selected(old('status', $rule->status ?? 'active') === 'active')>Đang áp dụng</option><option value="inactive" @selected(old('status', $rule->status) === 'inactive')>Tạm ngừng</option></select></div></div>
        <label class="block" data-pricing-dimension="holiday"><input type="checkbox" name="stacks_with_weekend" value="1" @checked(old('stacks_with_weekend', $rule->stacks_with_weekend))> Ngày lễ được cộng thêm phụ thu cuối tuần</label>
    </section>

    @if($errors->any())<div class="rounded-xl bg-error/10 p-4 text-error" role="alert">Vui lòng kiểm tra lại các trường được báo lỗi.</div>@endif
    <div class="flex gap-3"><button class="admin-btn-primary">{{ $rule->exists ? 'Cập nhật' : 'Tạo quy tắc' }}</button><a href="{{ route('admin.pricing-rules.index') }}" class="admin-btn-secondary">Quay lại</a></div>
</form>
@if($rule->exists)<form method="POST" action="{{ route('admin.pricing-rules.status', $rule) }}" class="mx-auto mt-4 max-w-5xl">@csrf<input type="hidden" name="status" value="{{ $rule->status === 'active' ? 'inactive' : 'active' }}"><button class="admin-btn-secondary">{{ $rule->status === 'active' ? 'Tạm ngừng' : 'Kích hoạt' }}</button></form>@endif
@endsection
