@extends('layouts.admin')
@section('title', $layoutTemplate->name)
@section('page-title', 'Chi tiết mẫu sơ đồ')

@section('content')
@php
    $statusClass = match ($layoutTemplate->status) {
        'active' => 'border-success/35 bg-success/10 text-success',
        'archived' => 'border-slate-500/40 bg-slate-500/10 app-muted',
        default => 'border-warning/35 bg-warning/10 text-warning',
    };
@endphp

<div class="space-y-6">
    <header class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <a href="{{ route('admin.layout-templates.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-bold app-muted transition-colors hover:text-brand-start"><i class="ph ph-arrow-left" aria-hidden="true"></i>Thư viện mẫu</a>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="break-words text-3xl font-black app-text sm:text-4xl">{{ $layoutTemplate->name }}</h1>
                <span class="rounded-full border px-3 py-1 text-xs font-black uppercase tracking-wider {{ $statusClass }}">{{ $layoutTemplate->status_label }}</span>
            </div>
            <p class="mt-3 max-w-3xl leading-relaxed app-muted">{{ $layoutTemplate->description ?: 'Mẫu bố cục ghế dùng lại khi cấu hình phòng chiếu.' }}</p>
        </div>

        @can('layout_templates.manage')
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                @if($layoutTemplate->status !== 'archived')
                    <a class="btn-secondary" href="{{ route('admin.layout-templates.edit', $layoutTemplate) }}"><i class="ph ph-pencil-simple" aria-hidden="true"></i>Chỉnh sửa</a>
                @endif
                @if($layoutTemplate->status === 'draft')
                    <form method="POST" action="{{ route('admin.layout-templates.activate', $layoutTemplate) }}">@csrf
                        <button type="submit" class="btn-primary w-full"><i class="ph ph-check-circle" aria-hidden="true"></i>Đưa vào sử dụng</button>
                    </form>
                @endif
                @if($layoutTemplate->status !== 'archived')
                    <form method="POST" action="{{ route('admin.layout-templates.archive', $layoutTemplate) }}">@csrf
                        <button type="submit" class="btn-secondary w-full text-error"><i class="ph ph-archive" aria-hidden="true"></i>Lưu trữ</button>
                    </form>
                @endif
            </div>
        @endcan
    </header>

    <section class="app-card rounded-3xl border app-border p-5 sm:p-6" aria-labelledby="template-summary-title">
        <h2 id="template-summary-title" class="sr-only">Tổng quan mẫu</h2>
        <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach([
                ['Mã mẫu', $layoutTemplate->code, 'ph-hash'],
                ['Loại phòng', $roomTypeName ?: 'Mọi loại phòng', 'ph-projector-screen'],
                ['Lưới logic', $layoutTemplate->rows.' hàng × '.$layoutTemplate->columns.' cột', 'ph-grid-four'],
                ['Vị trí ghế vật lý', $statistics['physical_seats'].' vị trí', 'ph-users-three'],
            ] as [$label, $value, $icon])
                <div class="min-w-0 rounded-2xl border app-border app-bg p-4">
                    <dt class="flex items-center gap-2 text-xs font-bold app-muted"><i class="ph {{ $icon }} text-brand-start" aria-hidden="true"></i>{{ $label }}</dt>
                    <dd class="mt-2 break-words text-lg font-black app-text">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="app-card rounded-3xl border app-border p-5 sm:p-6" aria-labelledby="template-statistics-title">
        <div class="mb-5">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Thống kê</p>
            <h2 id="template-statistics-title" class="mt-2 text-xl font-extrabold app-text">Cấu trúc ghế và phạm vi sử dụng</h2>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                ['Vị trí ghế vật lý', $statistics['physical_seats'], 'vị trí', 'ph-users-three'],
                ['Đơn vị tính giá', $statistics['pricing_units'], 'đơn vị', 'ph-ticket'],
                ['Ghế thường', $statistics['normal'], 'vị trí', 'ph-armchair'],
                ['VIP', $statistics['vip'], 'vị trí', 'ph-star'],
                ['Ghế đôi', $statistics['couple_pairs'], $statistics['couple_positions'].' vị trí', 'ph-heart'],
                ['Lối đi', $statistics['aisles'], 'ô', 'ph-arrows-down-up'],
                ['Đang áp dụng', $statistics['usages'], 'phòng/layout', 'ph-buildings'],
            ] as [$label, $value, $note, $icon])
                <div class="rounded-2xl border app-border app-bg p-4">
                    <p class="flex items-center gap-2 text-xs font-bold app-muted"><i class="ph {{ $icon }} text-brand-start" aria-hidden="true"></i>{{ $label }}</p>
                    <p class="mt-2 text-2xl font-black app-text">{{ $value }}</p>
                    <p class="mt-1 text-xs app-muted">{{ $note }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="app-card rounded-3xl border app-border p-4 sm:p-6" aria-labelledby="template-layout-title">
        <div class="mb-5">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Sơ đồ chỉ đọc</p>
            <h2 id="template-layout-title" class="mt-2 text-xl font-extrabold app-text">Bố cục ghế</h2>
            <div class="mt-4"><x-admin.layout-template-legend /></div>
        </div>
        <x-admin.layout-template-preview :template="$layoutTemplate" />
    </section>

    <section class="app-card rounded-3xl border app-border p-5 sm:p-6" aria-labelledby="template-usage-title">
        <div class="mb-5">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Lịch sử áp dụng</p>
            <h2 id="template-usage-title" class="mt-2 text-xl font-extrabold app-text">Các phòng đã dùng mẫu</h2>
        </div>
        @forelse($usages as $layout)
            <article class="flex flex-col gap-2 border-b app-border py-4 first:pt-0 last:border-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="font-extrabold app-text">{{ $layout->display_name }}</p><p class="mt-1 text-sm app-muted">{{ $layout->room->cinema->name }} / {{ $layout->room->name }}</p></div>
                <span class="w-fit rounded-full border app-border px-3 py-1 text-xs font-bold app-muted">{{ $layout->status_label }}</span>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed app-border app-bg px-5 py-10 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"><i class="ph-fill ph-projector-screen text-3xl" aria-hidden="true"></i></span>
                <h3 class="mt-4 text-lg font-extrabold app-text">Mẫu này chưa được áp dụng cho phòng chiếu nào.</h3>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-relaxed app-muted">Khi áp dụng mẫu cho một phòng, lịch sử sẽ xuất hiện tại đây.</p>
            </div>
        @endforelse
        @if($usages->hasPages())<div class="mt-5">{{ $usages->links() }}</div>@endif
    </section>
</div>
@endsection
