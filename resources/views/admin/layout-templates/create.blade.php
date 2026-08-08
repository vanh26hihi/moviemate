@extends('layouts.admin')
@section('title', 'Tạo mẫu sơ đồ')
@section('page-title', 'Tạo mẫu sơ đồ phòng')
@section('suppress-global-validation-summary', true)

@section('content')
<div class="space-y-6">
    <header>
        <a href="{{ route('admin.layout-templates.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-bold app-muted transition-colors hover:text-brand-start"><i class="ph ph-arrow-left" aria-hidden="true"></i>Thư viện mẫu</a>
        <h1 class="text-3xl font-black app-text sm:text-4xl">Tạo mẫu sơ đồ phòng</h1>
        <p class="mt-2 max-w-3xl leading-relaxed app-muted">Tạo bố cục ghế có thể tái sử dụng khi cấu hình phòng chiếu.</p>
    </header>

    @include('admin.layout-templates.form', [
        'action' => route('admin.layout-templates.store'),
        'method' => 'POST',
        'submitLabel' => 'Tạo mẫu sơ đồ',
    ])
</div>
@endsection
