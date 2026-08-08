@extends('layouts.admin')
@section('title', 'Bảng giá vé - MovieMate')
@section('page-title', 'Bảng giá vé')
@section('content')
<div class="admin-page-header"><div><h1 class="admin-page-title">Bảng giá vé</h1><p class="admin-page-subtitle">Quản lý giá cơ bản và các khoản phụ thu áp dụng theo chi nhánh, phòng, loại ghế, khung giờ và ngày chiếu.</p></div>@can('pricing.manage')<a class="admin-btn-primary" href="{{ route('admin.pricing-rules.create') }}">Thêm quy tắc</a>@endcan</div>

<form method="GET" class="app-card mb-6 grid gap-3 rounded-2xl border app-border p-4 md:grid-cols-4">
    <select name="cinema_id" class="cinema-input"><option value="">Mọi chi nhánh</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected(request('cinema_id') == $cinema->id)>{{ $cinema->name }}</option>@endforeach</select>
    <select name="room_id" class="cinema-input"><option value="">Mọi phòng</option>@foreach($rooms as $room)<option value="{{ $room->id }}" @selected(request('room_id') == $room->id)>{{ $room->code }} · {{ $room->name }}</option>@endforeach</select>
    <select name="rule_type" class="cinema-input"><option value="">Mọi loại quy tắc</option>@foreach(\App\Models\CinemaPricingRule::TYPES as $type)<option value="{{ $type }}" @selected(request('rule_type') === $type)>{{ $type }}</option>@endforeach</select>
    <select name="status" class="cinema-input"><option value="">Mọi trạng thái</option><option value="active" @selected(request('status') === 'active')>Đang áp dụng</option><option value="inactive" @selected(request('status') === 'inactive')>Tạm ngừng</option></select>
    <select name="seat_type" class="cinema-input"><option value="">Mọi loại ghế</option>@foreach(\App\Models\Seat::TYPES as $type)<option value="{{ $type }}" @selected(request('seat_type') === $type)>{{ $type }}</option>@endforeach</select>
    <select name="room_type" class="cinema-input"><option value="">Mọi loại phòng</option>@foreach(['2D','3D','IMAX'] as $type)<option value="{{ $type }}" @selected(request('room_type') === $type)>{{ $type }}</option>@endforeach</select>
    <input name="special_date" type="date" value="{{ request('special_date') }}" class="cinema-input" aria-label="Ngày đặc biệt">
    <button class="admin-btn-secondary" type="submit">Lọc bảng giá</button>
</form>

<div class="app-card overflow-x-auto rounded-2xl border app-border"><table class="admin-table w-full"><thead><tr><th>Tên quy tắc</th><th>Phạm vi</th><th>Loại</th><th>Điều kiện</th><th class="text-right">Mức giá/phụ thu</th><th>Ưu tiên</th><th>Hiệu lực</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($rules as $rule)
<tr><td class="font-bold app-text">{{ $rule->name }}</td><td>{{ $rule->room ? 'Phòng '.$rule->room->code : ($rule->cinema ? $rule->cinema->name : 'Toàn hệ thống') }}</td><td>{{ $rule->rule_type }}</td><td>{{ collect([$rule->seat_type, $rule->room_type, $rule->time_start && $rule->time_end ? substr($rule->time_start,0,5).'–'.substr($rule->time_end,0,5) : null, $rule->date_start ? $rule->date_start->format('d/m/Y').($rule->date_end && !$rule->date_end->equalTo($rule->date_start) ? '–'.$rule->date_end->format('d/m/Y') : '') : null])->filter()->implode(' · ') ?: 'Không có điều kiện bổ sung' }}</td><td class="text-right font-bold">{{ number_format((int)$rule->amount_vnd, 0, ',', '.') }} VNĐ</td><td>{{ $rule->priority }}</td><td>{{ $rule->starts_at?->format('d/m/Y H:i') ?? 'Ngay' }} – {{ $rule->ends_at?->format('d/m/Y H:i') ?? 'Không hạn' }}</td><td>{{ $rule->status === 'active' ? 'Đang áp dụng' : 'Tạm ngừng' }}</td><td>@can('pricing.manage')<a class="text-brand-start" href="{{ route('admin.pricing-rules.edit', $rule) }}">Sửa</a>@endcan</td></tr>
@empty<tr><td colspan="9" class="p-8 text-center app-muted">Chưa có quy tắc giá phù hợp.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $rules->links() }}</div>

<section class="app-card mt-8 rounded-2xl border app-border p-5"><h2 class="text-lg font-bold app-text">Xem trước giá vé</h2><p class="mt-1 text-sm app-muted">Kết quả đọc trực tiếp từ cùng dịch vụ định giá dùng trong checkout.</p>
<form id="pricing-preview-form" class="mt-4 grid gap-3 md:grid-cols-5">@csrf
<select name="cinema_id" class="cinema-input" required><option value="">Chi nhánh</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}">{{ $cinema->name }}</option>@endforeach</select>
<select name="room_id" class="cinema-input" required><option value="">Phòng</option>@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->code }}</option>@endforeach</select>
<input name="show_date" type="date" class="cinema-input" required><input name="show_time" type="time" class="cinema-input" required>
<select name="seat_type" class="cinema-input" required>@foreach(\App\Models\Seat::TYPES as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select>
<button class="admin-btn-primary md:col-span-5" type="submit">Tính giá</button></form><div id="pricing-preview-result" class="mt-4 text-sm app-text" aria-live="polite"></div></section>
@endsection
@push('scripts')<script>document.getElementById('pricing-preview-form')?.addEventListener('submit', async event => { event.preventDefault(); const output=document.getElementById('pricing-preview-result'); output.textContent='Đang tính…'; const response=await fetch(@json(route('admin.pricing-rules.preview')), {method:'POST',headers:{'Accept':'application/json'},body:new FormData(event.currentTarget)}); const data=await response.json(); if(!response.ok){output.textContent=data.message || Object.values(data.errors||{}).flat()[0] || 'Không thể tính giá.';return;} output.innerHTML=`<strong>Giá cơ bản:</strong> ${Number(data.base_amount).toLocaleString('vi-VN')} VNĐ<br>${data.surcharges.map(item=>`${item.label}: ${Number(item.amount).toLocaleString('vi-VN')} VNĐ (${item.rule_name})`).join('<br>')}<br><strong>Giá cuối:</strong> ${Number(data.final_amount).toLocaleString('vi-VN')} VNĐ`; });</script>@endpush
