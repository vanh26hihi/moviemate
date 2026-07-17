@extends('layouts.admin')

@section('title', 'Quản lý món ăn - MovieMate Admin')
@section('page-title', 'Quản lý món ăn')

@section('content')
<div class="admin-page-header gap-4 flex-col lg:flex-row lg:items-end">
    <div>
        <h1 class="admin-page-title">Quản lý món ăn</h1>
        <p class="admin-page-subtitle">Thêm, sửa và quản lý danh sách đồ ăn phục vụ khách tại rạp.</p>
    </div>
    <div class="w-full lg:w-auto flex flex-col sm:flex-row items-stretch gap-3">
        <form action="{{ route('admin.foods.index') }}" method="GET" class="flex flex-1 min-w-0 gap-2">
            <input type="search" name="search" value="{{ old('search', $search ?? '') }}" placeholder="Tìm kiếm món ăn, mô tả..." class="admin-input flex-1 min-w-0" />
            <button type="submit" class="admin-btn-secondary whitespace-nowrap">
                <i class="ph ph-magnifying-glass"></i>
                Tìm kiếm
            </button>
        </form>
        <a href="{{ route('admin.foods.create') }}" class="admin-btn-primary whitespace-nowrap">
            <i class="ph-bold ph-plus-circle"></i>
            Thêm món mới
        </a>
    </div>
</div>

@if(session('success'))
    <div class="mb-5 rounded-2xl border border-success/30 bg-success/10 text-success px-4 py-3 text-sm">
        {{ session('success') }}
    </div>
@endif

<div class="admin-card">
    <div class="overflow-x-auto rounded-3xl border app-border bg-app-card">
        <table class="w-full text-left border-collapse whitespace-nowrap">
            <thead>
                <tr class="app-secondary text-xs uppercase tracking-wider app-muted border-b app-border">
                    <th class="px-5 py-3 font-semibold">Món</th>
                    <th class="px-5 py-3 font-semibold">Giá</th>
                    <th class="px-5 py-3 font-semibold">Trạng thái</th>
                    <th class="px-5 py-3 font-semibold text-right">Hành động</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)] text-sm">
                @forelse($foods as $food)
                    <tr class="hover:bg-brand-start/5 transition-colors">
                        <td class="px-5 py-4">
                            <div class="font-semibold">{{ $food->name }}</div>
                            <div class="text-xs app-muted">{{ Str::limit($food->description, 80) }}</div>
                        </td>
                        <td class="px-5 py-4 font-semibold">{{ number_format($food->price,2) }}đ</td>
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center justify-center rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider {{ $food->active ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">
                                {{ $food->active ? 'Hoạt động' : 'Ẩn' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right space-x-2">
                            <a href="{{ route('admin.foods.edit', $food) }}" class="inline-flex items-center gap-2 rounded-xl border app-border app-muted px-3 py-2 text-sm hover:app-text hover:border-brand-start transition-colors">
                                <i class="ph ph-pencil"></i>
                                Sửa
                            </a>
                            <form action="{{ route('admin.foods.destroy', $food) }}" method="POST" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-2 rounded-xl border app-border border-error/20 text-error px-3 py-2 text-sm hover:bg-error/10 transition-colors" onclick="return confirm('Xác nhận xóa món này?')">
                                    <i class="ph ph-trash"></i>
                                    Xóa
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-8 text-center app-muted">Chưa có món ăn nào. Hãy thêm món mới.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $foods->links() }}
    </div>
</div>
@endsection