@extends('layouts.admin')

@php($editing = $cinema->exists)
@section('title', ($editing ? 'Sửa chi nhánh' : 'Thêm chi nhánh').' - MovieMate')
@section('page-title', $editing ? 'Sửa chi nhánh' : 'Thêm chi nhánh')

@section('content')
<form method="POST" action="{{ $editing ? route('admin.cinemas.update', $cinema) : route('admin.cinemas.store') }}" class="admin-form-card max-w-4xl">
    @csrf @if($editing) @method('PATCH') @endif
    <div class="grid gap-5 md:grid-cols-2">
        @foreach([['code','Mã chi nhánh','CG'],['name','Tên chi nhánh','MovieMate Cầu Giấy'],['address','Địa chỉ','Số nhà, đường, quận/huyện'],['city','Tỉnh / thành phố','Hà Nội'],['district','Quận / huyện','Cầu Giấy'],['country','Quốc gia','Việt Nam'],['phone','Điện thoại','']] as [$field,$label,$placeholder])
            <div class="{{ $field === 'address' ? 'md:col-span-2' : '' }}"><label class="admin-label" for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $cinema->{$field}) }}" class="admin-input" placeholder="{{ $placeholder }}" {{ in_array($field, ['code','name','address','city']) ? 'required' : '' }}>@error($field)<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror</div>
        @endforeach
        <div><label class="admin-label" for="timezone">Múi giờ</label><input id="timezone" name="timezone" value="{{ old('timezone', $cinema->timezone ?: 'Asia/Ho_Chi_Minh') }}" class="admin-input" required>@error('timezone')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror</div>
        <div><label class="admin-label" for="status">Trạng thái</label><select id="status" name="status" class="admin-input"><option value="active" @selected(old('status', $cinema->status ?: 'active') === 'active')>Đang hoạt động</option><option value="inactive" @selected(old('status', $cinema->status) === 'inactive')>Ngừng hoạt động</option></select></div>
        <div class="md:col-span-2"><label class="admin-label" for="description">Mô tả</label><textarea id="description" name="description" rows="4" class="admin-input">{{ old('description', $cinema->description) }}</textarea></div>
    </div>
    <div class="mt-6 flex gap-3"><button class="admin-btn-primary" type="submit">{{ $editing ? 'Lưu thay đổi' : 'Tạo chi nhánh' }}</button><a href="{{ route('admin.cinemas.index') }}" class="admin-btn-secondary">Hủy</a></div>
</form>
@endsection
