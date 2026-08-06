@extends('layouts.admin')

@section('title', 'Bảo trì ghế theo phòng - MovieMate')
@section('page-title', 'Bảo trì ghế theo phòng')

@section('content')
<div class="cinema-card mx-auto max-w-3xl p-8 text-center"><i class="ph ph-door-open text-5xl text-brand-start"></i><h1 class="mt-4 text-2xl font-extrabold app-text">Bảo trì ghế được quản lý theo từng phòng</h1><p class="mt-2 app-muted">Chọn một phòng để xem đúng sơ đồ hiện hành và các ràng buộc giữ chỗ, vé đã bán.</p><a href="{{ route('admin.rooms.index') }}" class="btn-primary mt-6">Đến danh sách phòng</a></div>
@endsection
