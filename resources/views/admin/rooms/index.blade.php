@extends('layouts.admin')

@section('title', __('rooms.index_title').' - MovieMate')
@section('page-title', __('rooms.index_title'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="mb-2 text-sm font-extrabold uppercase tracking-[0.22em] text-brand-start">Phòng chiếu</p>
            <h1 class="app-text text-3xl font-extrabold">{{ __('rooms.index_title') }}</h1>
            <p class="app-muted mt-2">{{ __('rooms.description') }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @can('room_types.view')<a href="{{ route('admin.room-types.index') }}" class="btn-secondary"><i class="ph ph-stack" aria-hidden="true"></i> Quản lý loại phòng</a>@endcan
            @can('rooms.create')
                <a href="{{ route('admin.rooms.create') }}" class="btn-primary" title="{{ __('rooms.add') }}">
                    <i class="ph-bold ph-plus" aria-hidden="true"></i> {{ __('rooms.add') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="cinema-card overflow-hidden">
        <div class="border-b app-border p-5">
            <form method="GET" action="{{ route('admin.rooms.index') }}" class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(240px,1fr)_220px_180px_auto_auto]">
                <label class="flex items-center gap-3 rounded-2xl border app-border app-input px-4">
                    <i class="ph ph-magnifying-glass app-muted" aria-hidden="true"></i>
                    <span class="sr-only">{{ __('common.search') }}</span>
                    <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('rooms.search_placeholder') }}" class="w-full bg-transparent py-3 app-text focus:outline-none">
                </label>
                <label>
                    <span class="sr-only">{{ __('rooms.fields.status') }}</span>
                    <select name="status" class="cinema-input">
                        <option value="">{{ __('rooms.all_statuses') }}</option>
                        <option value="active" @selected($status === 'active')>{{ \App\Support\StatusLabel::for('room', 'active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ \App\Support\StatusLabel::for('room', 'inactive') }}</option>
                    </select>
                </label>
                <label>
                    <span class="sr-only">{{ __('rooms.fields.type') }}</span>
                    <select name="room_type" class="cinema-input">
                        <option value="">{{ __('rooms.all_types') }}</option>
                        @foreach($roomTypes as $type)
                            <option value="{{ $type->code }}" @selected($roomType === $type->code)>{{ $type->name }}{{ $type->is_active ? '' : ' · Đã lưu trữ' }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn-secondary !rounded-2xl">
                    <i class="ph ph-funnel" aria-hidden="true"></i> {{ __('rooms.filter') }}
                </button>
                @if($search !== '' || $status !== '' || $roomType !== '')
                    <a href="{{ route('admin.rooms.index') }}" class="btn-secondary !rounded-2xl">{{ __('rooms.clear_filter') }}</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="admin-table min-w-[1180px]">
                <thead>
                    <tr>
                        <th>{{ __('rooms.fields.code') }}</th>
                        <th>{{ __('rooms.fields.name') }}</th>
                        <th>{{ __('rooms.fields.cinema') }}</th>
                        <th>{{ __('rooms.fields.type') }}</th>
                        <th>{{ __('rooms.fields.layout') }}</th>
                        <th>{{ __('rooms.fields.total_seats') }}</th>
                        <th>{{ __('rooms.fields.normal_seats') }}</th>
                        <th>{{ __('rooms.fields.vip_seats') }}</th>
                        <th>{{ __('rooms.fields.couple_seats') }}</th>
                        <th>{{ __('rooms.fields.status') }}</th>
                        <th>{{ __('rooms.fields.upcoming_showtimes') }}</th>
                        <th class="text-right">{{ __('rooms.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rooms as $room)
                        @php
                            $published = $room->latestPublishedLayout;
                            $publishedSeats = $published?->cells->where('cell_type', 'seat')->pluck('seat')->filter() ?? collect();
                        @endphp
                        <tr>
                            <td class="font-bold app-text">{{ $room->code }}</td>
                            <td class="font-extrabold app-text">{{ $room->name }}</td>
                            <td>
                                <p class="font-bold app-text">{{ $room->cinema->name ?? '—' }}</p>
                                <p class="text-xs app-muted">{{ $room->cinema->city ?? '' }}</p>
                            </td>
                            <td>{{ $room->room_type_label }}</td>
                            <td>
                                @if($published)
                                    <p class="font-bold app-text">Phiên bản {{ $published->version }} · {{ $published->rows }} × {{ $published->columns }}</p>
                                    <p class="text-xs app-muted">{{ $published->status_label }}</p>
                                @else
                                    <span class="text-xs text-warning">{{ __('rooms.no_layout') }}</span>
                                @endif
                                @if($room->draftLayout)
                                    <span class="status-badge mt-1 inline-block bg-warning/10 text-warning">{{ __('rooms.has_draft', ['version' => $room->draftLayout->version]) }}</span>
                                @endif
                            </td>
                            <td>{{ $publishedSeats->count() }}</td>
                            <td>{{ $publishedSeats->where('type', 'normal')->count() }}</td>
                            <td>{{ $publishedSeats->where('type', 'vip')->count() }}</td>
                            <td>{{ $publishedSeats->where('type', 'couple')->count() }}</td>
                            <td>
                                <span class="status-badge {{ $room->status === 'active' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">{{ $room->status_label }}</span>
                            </td>
                            <td>{{ $room->upcoming_showtimes_count }}</td>
                            <td>
                                <div class="flex min-w-max items-center justify-end gap-2">
                                    @can('rooms.view')
                                        <a href="{{ route('admin.rooms.show', $room) }}" class="btn-secondary !rounded-xl !px-3 !py-2 text-xs" title="{{ __('rooms.actions.view') }}" aria-label="{{ __('rooms.actions.view') }} {{ $room->name }}">
                                            <i class="ph ph-eye" aria-hidden="true"></i> {{ __('rooms.actions.view') }}
                                        </a>
                                    @endcan
                                    @can('seats.manage')
                                        @if($room->status === 'active')
                                            <a href="{{ route('admin.rooms.layout.show', $room) }}" class="btn-secondary !rounded-xl !px-3 !py-2 text-xs" title="{{ __('rooms.actions.layout') }}" aria-label="{{ __('rooms.actions.layout') }} {{ $room->name }}">
                                                <i class="ph ph-grid-four" aria-hidden="true"></i> {{ __('rooms.actions.layout') }}
                                            </a>
                                        @elseif($published)
                                            <a href="{{ route('admin.rooms.layout.preview', ['room' => $room, 'version' => $published->version]) }}" class="btn-secondary !rounded-xl !px-3 !py-2 text-xs" title="{{ __('rooms.actions.preview') }}" aria-label="{{ __('rooms.actions.preview') }} {{ $room->name }}">
                                                <i class="ph ph-grid-four" aria-hidden="true"></i> {{ __('rooms.actions.preview') }}
                                            </a>
                                        @endif
                                    @endcan
                                    @can('seats.maintenance.view')
                                        @if($room->status === 'active' && $published)
                                            <a href="{{ route('admin.rooms.seat-maintenance.index', $room) }}" class="btn-secondary !rounded-xl !px-3 !py-2 text-xs" title="Bảo trì ghế" aria-label="Bảo trì ghế {{ $room->name }}">
                                                <i class="ph ph-wrench" aria-hidden="true"></i> Bảo trì ghế
                                            </a>
                                        @endif
                                    @endcan
                                    @can('rooms.update')
                                        <a href="{{ route('admin.rooms.edit', $room) }}" class="btn-secondary !rounded-xl !px-3 !py-2 text-xs" title="{{ __('rooms.actions.edit') }}" aria-label="{{ __('rooms.actions.edit') }} {{ $room->name }}">
                                            <i class="ph ph-pencil-simple" aria-hidden="true"></i> {{ __('rooms.actions.edit') }}
                                        </a>
                                        <form action="{{ route('admin.rooms.status.update', $room) }}" method="POST" onsubmit="return confirm(@js($room->status === 'active' ? __('rooms.confirm.deactivate') : __('rooms.confirm.activate')));">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $room->status === 'active' ? 'inactive' : 'active' }}">
                                            <button type="submit" class="btn-secondary !rounded-xl !px-3 !py-2 text-xs" title="{{ $room->status === 'active' ? __('rooms.actions.deactivate') : __('rooms.actions.activate') }}" aria-label="{{ $room->status === 'active' ? __('rooms.actions.deactivate') : __('rooms.actions.activate') }} {{ $room->name }}">
                                                {{ $room->status === 'active' ? __('rooms.actions.deactivate') : __('rooms.actions.activate') }}
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="py-12 text-center app-muted">
                                <i class="ph ph-armchair block text-4xl" aria-hidden="true"></i>
                                <span class="mt-2 block">{{ __('rooms.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rooms->hasPages())
            <div class="border-t app-border p-5">{{ $rooms->links() }}</div>
        @endif
    </div>
</div>
@endsection
