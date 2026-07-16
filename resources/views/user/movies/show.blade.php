@extends('layouts.user')

@section('title', $movie->title . ' - MovieMate')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-1">
                <div class="poster-frame rounded-2xl overflow-hidden app-card border app-border">
                    <img src="{{ $movie->poster ? asset('storage/' . $movie->poster) : asset('images/placeholder.png') }}"
                         alt="{{ $movie->title }}"
                         class="w-full h-full object-cover">
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="{{ $movie->status === 'now_showing' ? 'bg-brand-start' : 'bg-ai-start' }} text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                        {{ $movie->status === 'now_showing' ? 'Đang chiếu' : 'Sắp chiếu' }}
                    </span>
                    <span class="border app-border app-text text-xs font-bold px-3 py-1 rounded-full">
                        {{ $movie->age_rating }}
                    </span>
                </div>

                <h1 class="text-3xl md:text-5xl font-bold app-text mb-4">{{ $movie->title }}</h1>

                <div class="flex flex-wrap items-center gap-5 text-sm app-muted mb-6">
                    <div class="flex items-center gap-2">
                        <i class="ph ph-clock text-lg"></i> {{ $movie->duration }} phút
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ph ph-calendar-blank text-lg"></i>
                        Khởi chiếu:
                        {{ $movie->release_date ? $movie->release_date->format('d/m/Y') : 'Chưa xác định' }}
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ph ph-globe-hemisphere-west text-lg"></i> {{ $movie->country ?? 'Đang cập nhật' }}
                    </div>
                </div>

                <div class="app-card border app-border rounded-2xl p-5 mb-6">
                    <h2 class="text-xl font-bold app-text mb-3 border-l-4 border-brand-start pl-3">Nội dung phim</h2>
                    <p class="app-muted leading-relaxed">
                        {{ $movie->description ?? 'Nội dung phim đang được cập nhật.' }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2 text-sm">
                        @forelse($movie->genres as $genre)
                            <span class="px-3 py-1 app-secondary border app-border rounded-lg app-muted">
                                {{ $genre->name }}
                            </span>
                        @empty
                            <span class="app-muted">Chưa cập nhật thể loại</span>
                        @endforelse
                    </div>
                </div>

                @if($movie->trailer_url)
                    <a href="{{ $movie->trailer_url }}" target="_blank"
                       class="inline-flex items-center gap-2 px-5 py-3 app-card border app-border app-text rounded-xl hover:border-brand-start hover:text-brand-start transition-colors">
                        <i class="ph-fill ph-play-circle text-2xl"></i> Xem trailer
                    </a>
                @endif
            </div>
        </div>

        <section id="showtimes" class="mt-12">
            <h2 class="text-2xl font-bold app-text mb-6 border-l-4 border-brand-start pl-4">Lịch chiếu</h2>

            @if($showtimes->isEmpty())
                <div class="app-card border app-border rounded-2xl p-6 app-muted">
                    Hiện chưa có suất chiếu khả dụng.
                </div>
            @else
                <div class="app-card border app-border rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="app-secondary">
                                <tr>
                                    <th class="px-4 py-3 app-text text-sm font-semibold">Ngày</th>
                                    <th class="px-4 py-3 app-text text-sm font-semibold">Giờ</th>
                                    <th class="px-4 py-3 app-text text-sm font-semibold">Rạp / Phòng</th>
                                    <th class="px-4 py-3 app-text text-sm font-semibold">Giá thường</th>
                                    <th class="px-4 py-3 app-text text-sm font-semibold">Giá VIP</th>
                                    <th class="px-4 py-3 app-text text-sm font-semibold text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($showtimes as $show)
                                    <tr class="border-t app-border">
                                        <td class="px-4 py-3 app-text">
                                            {{ $show->show_date->format('d/m/Y') }}
                                        </td>
                                        <td class="px-4 py-3 app-text font-bold text-brand-start">
                                            {{ \Carbon\Carbon::parse($show->show_time)->format('H:i') }}
                                        </td>
                                        <td class="px-4 py-3 app-muted">
                                            {{ $show->cinema->name }} / {{ $show->room->name }}
                                        </td>
                                        <td class="px-4 py-3 app-text">
                                            {{ number_format($show->price, 0, ',', '.') }}đ
                                        </td>
                                        <td class="px-4 py-3 app-text">
                                            {{ number_format($show->vip_price ?? $show->price, 0, ',', '.') }}đ
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('user.bookings.selectSeat', $show->id) }}"
                                               class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-brand-start to-brand-end text-white rounded-xl font-semibold text-sm hover:shadow-lg hover:shadow-brand-start/30 transition-all">
                                                Chọn ghế
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection