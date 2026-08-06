@php
    $showtimes = collect($showtimes ?? []);
    $money = fn (int $amount) => number_format($amount, 0, ',', '.').' ₫';
    $time = fn ($value) => \Carbon\Carbon::parse($value)->format('H:i');
@endphp

@if($showtimes->isEmpty())
    <div class="cinema-card rounded-3xl p-8 text-center" data-showtime-empty>
        <i class="ph-fill ph-calendar-x text-4xl text-brand-start" aria-hidden="true"></i>
        <h3 class="mt-4 text-xl font-extrabold app-text">
            {{ $context === 'cinema' ? 'Chi nhánh này chưa có lịch chiếu trong ngày đã chọn.' : 'Phim này chưa có suất chiếu tại chi nhánh đã chọn.' }}
        </h3>
        <p class="mt-2 app-muted">Hãy chọn ngày hoặc rạp khác trong 14 ngày tới.</p>
    </div>
@elseif($context === 'cinema')
    <div class="space-y-5">
        @foreach($showtimes->groupBy('movie_id') as $movieShowtimes)
            @php($resultMovie = $movieShowtimes->first()->movie)
            <article class="cinema-card rounded-3xl p-5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-xl font-extrabold app-text">{{ $resultMovie->title }}</h3>
                        <p class="mt-1 text-sm app-muted">{{ $resultMovie->genres->pluck('name')->join(', ') ?: 'Đang cập nhật thể loại' }} · {{ $resultMovie->duration ?? '—' }} phút</p>
                    </div>
                    <a href="{{ route('user.movies.show', ['slug' => $resultMovie->slug, 'cinema' => $cinema->code, 'date' => $selectedDate]) }}" class="text-sm font-bold text-brand-start">Xem phim <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="mt-5 space-y-4">
                    @foreach($movieShowtimes->groupBy(fn ($showtime) => $showtime->room->room_type ?: '2D') as $format => $formatShowtimes)
                        <div><p class="mb-2 text-xs font-extrabold uppercase tracking-wider app-muted">{{ $format }}</p><div class="flex flex-wrap gap-2">
                            @foreach($formatShowtimes as $showtime)
                                <a href="{{ route('user.bookings.selectSeat', ['showtime' => $showtime, 'cinema' => $showtime->cinema->code]) }}" class="rounded-xl border border-brand-start/30 bg-brand-start/10 px-4 py-3 font-extrabold text-brand-start hover:bg-brand-start hover:text-white">
                                    <span>{{ $time($showtime->show_time) }}</span><span class="block text-xs font-semibold opacity-80">{{ $showtime->room->name }} · Từ {{ $money((int) $showtime->starting_price) }}</span>
                                </a>
                            @endforeach
                        </div></div>
                    @endforeach
                </div>
            </article>
        @endforeach
    </div>
@else
    <div class="space-y-5">
        @foreach($showtimes->groupBy('cinema_id') as $cinemaShowtimes)
            @php($resultCinema = $cinemaShowtimes->first()->cinema)
            <article class="cinema-card rounded-3xl p-5 sm:p-6" data-cinema-code="{{ $resultCinema->code }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div><h3 class="text-xl font-extrabold app-text">{{ $resultCinema->name }}</h3><p class="mt-1 text-sm app-muted">{{ $resultCinema->address }}</p></div>
                    <a href="{{ route('cinemas.show', ['cinema' => $resultCinema->code, 'date' => $selectedDate]) }}" class="text-sm font-bold text-brand-start">Xem rạp <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
                </div>
                @foreach($cinemaShowtimes->groupBy('movie_id') as $resultMovieShowtimes)
                    @php($resultMovie = $resultMovieShowtimes->first()->movie)
                    @if($context === 'home')<h4 class="mt-5 font-extrabold app-text">{{ $resultMovie->title }}</h4>@endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($resultMovieShowtimes as $showtime)
                            <a href="{{ route('user.bookings.selectSeat', ['showtime' => $showtime, 'cinema' => $showtime->cinema->code]) }}" class="rounded-xl border border-brand-start/30 bg-brand-start/10 px-4 py-3 font-extrabold text-brand-start hover:bg-brand-start hover:text-white">
                                {{ $time($showtime->show_time) }} · {{ $showtime->room->room_type }}
                                <span class="block text-xs font-semibold opacity-80">Từ {{ $money((int) $showtime->starting_price) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </article>
        @endforeach
    </div>
@endif
