@extends('layouts.admin')

@section('title', 'Chi tiết phim')
@section('page-title', 'Chi tiết phim')

@php
    $statusMeta = [
        'draft' => [
            'label' => 'Bản nháp',
            'class' => 'bg-blue-500/10 text-blue-400 border border-blue-500/20',
        ],
        'coming_soon' => [
            'label' => 'Sắp chiếu',
            'class' => 'bg-warning/10 text-warning border border-warning/20',
        ],
        'now_showing' => [
            'label' => 'Đang chiếu',
            'class' => 'bg-success/10 text-success border border-success/20',
        ],
        'inactive' => [
            'label' => 'Ngừng chiếu',
            'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20',
        ],
        'archived' => [
            'label' => 'Đã lưu trữ',
            'class' => 'bg-slate-700/20 text-slate-400 border border-slate-600/20',
        ],
    ];

    $status = $statusMeta[$movie->status] ?? [
        'label' => $movie->status_label ?? $movie->status,
        'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20',
    ];

    $transitionLabels = [
        'draft' => 'Chuyển về bản nháp',
        'coming_soon' => 'Chuyển sang sắp chiếu',
        'now_showing' => 'Chuyển sang đang chiếu',
        'inactive' => 'Ngừng chiếu',
        'archived' => 'Lưu trữ phim',
    ];

    $transitionIcons = [
        'draft' => 'ph-note-pencil',
        'coming_soon' => 'ph-calendar',
        'now_showing' => 'ph-play-circle',
        'inactive' => 'ph-pause-circle',
        'archived' => 'ph-archive',
    ];
@endphp

@section('content')

@if(session('success'))
    <div class="mb-5 rounded-2xl border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
        {{ session('success') }}
    </div>
@endif

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="admin-page-title">
            Chi tiết phim
        </h1>

        <p class="admin-page-subtitle">
            Xem thông tin và quản lý trạng thái của phim.
        </p>
    </div>

    <div class="flex flex-wrap gap-2">

        <a
            href="{{ route('admin.movies.index') }}"
            class="admin-btn-info"
        >
            <i class="ph ph-arrow-left"></i>
            Danh sách phim
        </a>

        @if($movie->status !== 'archived')
            <a
                href="{{ route('admin.movies.edit', $movie) }}"
                class="admin-btn-warning"
            >
                <i class="ph ph-pencil-simple"></i>
                Sửa phim
            </a>
        @endif

    </div>
</div>


{{-- THÔNG TIN CHÍNH --}}
<div class="admin-detail-card mb-6">

    <div class="grid grid-cols-1 gap-6 p-5 sm:p-6 lg:grid-cols-12">

        {{-- Poster --}}
        <div class="lg:col-span-3">

            <div class="mx-auto max-w-[220px] overflow-hidden rounded-2xl border app-border bg-slate-950 shadow-2xl shadow-black/20">

                <div class="aspect-[2/3]">

                    @if($movie->poster_url)

                        <img
                            src="{{ $movie->poster_url }}"
                            alt="{{ $movie->title }}"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        >

                    @else

                        <div class="admin-media-fallback h-full w-full text-2xl">
                            MM
                        </div>

                    @endif

                </div>

            </div>

        </div>


        {{-- Chi tiết --}}
        <div class="lg:col-span-9">

            {{-- Badge trạng thái + thể loại --}}
            <div class="mb-4 flex flex-wrap items-center gap-2">

                <span class="admin-badge {{ $status['class'] }}">
                    {{ $status['label'] }}
                </span>

                @foreach($movie->genres as $genre)

                    <span class="admin-badge border border-brand-start/20 bg-brand-start/10 text-brand-start">
                        {{ $genre->name }}
                    </span>

                @endforeach

            </div>


            <h2 class="text-2xl font-extrabold app-heading sm:text-3xl">
                {{ $movie->title }}
            </h2>

            <p class="mt-2 font-mono text-sm app-text-muted">
                {{ $movie->slug }}
            </p>


            {{-- Thông tin --}}
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">

                <div class="rounded-2xl border app-border app-card-soft p-4">
                    <p class="text-xs font-bold uppercase tracking-wider app-text-muted">
                        Quốc gia
                    </p>

                    <p class="mt-1 font-bold app-text">
                        {{ $movie->country ?? 'Chưa cập nhật' }}
                    </p>
                </div>


                <div class="rounded-2xl border app-border app-card-soft p-4">
                    <p class="text-xs font-bold uppercase tracking-wider app-text-muted">
                        Thời lượng
                    </p>

                    <p class="mt-1 font-bold app-text">
                        {{ $movie->duration ?? 'Chưa cập nhật' }}
                        {{ $movie->duration ? ' phút' : '' }}
                    </p>
                </div>


                <div class="rounded-2xl border app-border app-card-soft p-4">
                    <p class="text-xs font-bold uppercase tracking-wider app-text-muted">
                        Độ tuổi
                    </p>

                    <p class="mt-1 font-bold app-text">
                        {{ $movie->age_rating ?? 'Chưa cập nhật' }}
                    </p>
                </div>


                <div class="rounded-2xl border app-border app-card-soft p-4">
                    <p class="text-xs font-bold uppercase tracking-wider app-text-muted">
                        Ngày khởi chiếu
                    </p>

                    <p class="mt-1 font-bold app-text">
                        {{ $movie->release_date
                            ? \Carbon\Carbon::parse($movie->release_date)->format('d/m/Y')
                            : 'Chưa cập nhật'
                        }}
                    </p>
                </div>


                <div class="rounded-2xl border app-border app-card-soft p-4">
                    <p class="text-xs font-bold uppercase tracking-wider app-text-muted">
                        Áp phích
                    </p>

                    <p class="mt-1 font-bold app-text">
                        {{ $movie->poster_url ? 'Đã có' : 'Chưa có' }}
                    </p>
                </div>


                <div class="rounded-2xl border app-border app-card-soft p-4">
                    <p class="text-xs font-bold uppercase tracking-wider app-text-muted">
                        Ảnh bìa
                    </p>

                    <p class="mt-1 font-bold app-text">
                        {{ $movie->cover_url ? 'Đã có' : 'Chưa có' }}
                    </p>
                </div>

            </div>

        </div>

    </div>

</div>


{{-- CẬP NHẬT TRẠNG THÁI --}}
<section class="admin-detail-card mb-6">

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h3 class="text-lg font-extrabold app-heading">
                Cập nhật trạng thái phim
            </h3>

            <p class="mt-1 text-sm app-text-muted">
                Trạng thái hiện tại:
                <span class="font-bold app-text">
                    {{ $status['label'] }}
                </span>
            </p>
        </div>

        <span class="admin-badge {{ $status['class'] }}">
            {{ $status['label'] }}
        </span>

    </div>


    @if(!empty($allowedTransitions) && count($allowedTransitions) > 0)

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">

            @foreach($allowedTransitions as $transition)

                @php
                    /*
                     * Trường hợp service trả về:
                     * ['coming_soon', 'now_showing']
                     *
                     * hoặc associative array:
                     * ['coming_soon' => ...]
                     */
                    $targetStatus = is_string($transition)
                        ? $transition
                        : (
                            is_array($transition)
                                ? ($transition['status'] ?? $transition['value'] ?? null)
                                : null
                        );

                    $targetMeta = $statusMeta[$targetStatus] ?? [
                        'label' => $targetStatus,
                        'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20',
                    ];

                    $buttonLabel = $transitionLabels[$targetStatus]
                        ?? ('Chuyển sang ' . ($targetMeta['label'] ?? $targetStatus));

                    $icon = $transitionIcons[$targetStatus] ?? 'ph-arrow-right';
                @endphp


                @if($targetStatus)

                    <form
                        action="{{ route('admin.movies.lifecycle', $movie) }}"
                        method="POST"
                        onsubmit="return confirm('Bạn có chắc muốn {{ strtolower($buttonLabel) }}?');"
                    >
                        @csrf
                        @method('PATCH')

                        <input
                            type="hidden"
                            name="status"
                            value="{{ $targetStatus }}"
                        >

                        <button
                            type="submit"
                            class="flex w-full items-center justify-between rounded-2xl border app-border app-card-soft p-4 text-left transition hover:border-brand-start"
                        >

                            <div class="flex items-center gap-3">

                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start">
                                    <i class="ph {{ $icon }} text-xl"></i>
                                </div>

                                <div>
                                    <p class="font-extrabold app-text">
                                        {{ $buttonLabel }}
                                    </p>

                                    <p class="mt-1 text-xs app-text-muted">
                                        {{ $targetMeta['label'] }}
                                    </p>
                                </div>

                            </div>

                            <i class="ph ph-arrow-right text-lg app-text-muted"></i>

                        </button>

                    </form>

                @endif

            @endforeach

        </div>

    @else

        <div class="rounded-2xl border app-border app-card-soft p-5">

            <div class="flex items-start gap-3">

                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-500/10 text-slate-400">
                    <i class="ph ph-info text-xl"></i>
                </div>

                <div>
                    <p class="font-bold app-text">
                        Không có trạng thái tiếp theo
                    </p>

                    <p class="mt-1 text-sm app-text-muted">
                        Phim hiện không có chuyển đổi trạng thái nào được phép.
                    </p>
                </div>

            </div>

        </div>

    @endif

</section>


{{-- MÔ TẢ + TRAILER --}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <section class="admin-detail-card lg:col-span-2">

        <h3 class="mb-3 text-lg font-extrabold app-heading">
            Mô tả phim
        </h3>

        <p class="whitespace-pre-line leading-relaxed app-text-muted">
            {{ $movie->description ?? 'Chưa có mô tả.' }}
        </p>

    </section>


    <section class="admin-detail-card">

        <h3 class="mb-3 text-lg font-extrabold app-heading">
            Video giới thiệu
        </h3>

        @if($movie->trailer_url)

            <p class="mb-4 text-sm app-text-muted">
                Mở trailer trong tab mới để kiểm tra nội dung hiển thị.
            </p>

            <a
                href="{{ $movie->trailer_url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="admin-btn-primary w-full"
            >
                <i class="ph-fill ph-play"></i>
                Xem trailer
            </a>

        @else

            <div class="rounded-2xl border app-border app-card-soft p-5 text-center app-text-muted">
                Chưa có trailer.
            </div>

        @endif

    </section>

</div>

@endsection