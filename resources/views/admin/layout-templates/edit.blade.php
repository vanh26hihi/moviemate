@extends('layouts.admin')
@section('title', 'Chỉnh sửa mẫu sơ đồ')
@section('page-title', 'Chỉnh sửa mẫu sơ đồ')
@section('suppress-global-validation-summary', true)

@section('content')
<div class="space-y-6">
    <header>
        <a href="{{ route('admin.layout-templates.show', $template) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-bold app-muted transition-colors hover:text-brand-start"><i class="ph ph-arrow-left" aria-hidden="true"></i>Chi tiết mẫu</a>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-black app-text sm:text-4xl">Chỉnh sửa {{ $template->name }}</h1>
            <span class="rounded-full border app-border app-card px-3 py-1 text-xs font-black uppercase tracking-wider text-brand-start">{{ $template->status_label }}</span>
        </div>
        <p class="mt-2 max-w-3xl leading-relaxed app-muted">Cập nhật thông tin và bố cục ghế; định dạng dữ liệu áp dụng cho phòng được giữ nguyên.</p>
    </header>

    @include('admin.layout-templates.form', [
        'action' => route('admin.layout-templates.update', $template),
        'method' => 'PUT',
        'submitLabel' => 'Lưu thay đổi',
    ])
</div>
@endsection
