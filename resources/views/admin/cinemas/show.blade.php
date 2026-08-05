@extends('layouts.admin')

@section('title', 'Thông tin rạp - MovieMate')
@section('page-title', 'Thông tin rạp')

@section('content')
<div class="admin-page-header"><div><h1 class="admin-page-title">{{ $cinema->name }}</h1><p class="admin-page-subtitle">{{ $cinema->school_name }}</p></div></div>
<div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
    <form method="POST" action="{{ route('admin.cinema.update') }}" enctype="multipart/form-data" class="admin-form-card">@csrf @method('PATCH')
        <div class="space-y-5">
            <div><label class="admin-label">Số điện thoại</label><input class="admin-input" name="phone" value="{{ old('phone', $cinema->phone) }}" placeholder="Chưa cập nhật"></div>
            <div><label class="admin-label">Mô tả</label><textarea class="admin-input" name="description" rows="5">{{ old('description', $cinema->description) }}</textarea></div>
            <div><label class="admin-label">Hình ảnh</label><input class="admin-input" type="file" name="image" accept="image/*"></div>
            @can('cinema.update')<button class="admin-btn-primary" type="submit"><i class="ph ph-floppy-disk"></i> Lưu thông tin</button>@endcan
        </div>
    </form>
    <section class="app-card border app-border rounded-2xl p-6 space-y-4">
        <h2 class="text-lg font-bold">Cấu hình cố định</h2>
        @foreach([
            'Tên rạp' => $cinema->name, 'Mã định danh hệ thống' => $cinema->canonical_key, 'Trường' => $cinema->school_name,
            'Địa chỉ' => $cinema->address, 'Thành phố' => $cinema->city, 'Quốc gia' => $cinema->country,
            'Vĩ độ' => $cinema->latitude, 'Kinh độ' => $cinema->longitude,
            'Trạng thái' => \App\Support\StatusLabel::for('generic', $cinema->status), 'Cơ sở chính' => $cinema->is_primary ? 'Có' : 'Không',
            'Phòng đang hoạt động' => $cinema->active_rooms_count, 'Tổng số phòng' => $cinema->rooms_count,
        ] as $label => $value)
            <div class="border-b app-border pb-3"><div class="text-xs uppercase app-muted">{{ $label }}</div><div class="font-semibold app-text mt-1">{{ $value ?: '—' }}</div></div>
        @endforeach
        <a href="{{ $cinema->map_url }}" target="_blank" rel="noopener" class="admin-btn-secondary"><i class="ph ph-map-trifold"></i> Xem bản đồ</a>
    </section>
</div>
@endsection
