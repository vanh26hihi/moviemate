@php($showtimes = collect($showtimes ?? []))

@if($showtimes->isEmpty())
    <div class="cinema-card rounded-3xl p-8 text-center" data-showtime-empty>
        <i class="ph-fill ph-calendar-x text-4xl text-brand-start" aria-hidden="true"></i>
        <h3 class="mt-4 text-xl font-extrabold app-text">Chưa có suất chiếu trong ngày đã chọn.</h3>
        <p class="mt-2 app-muted">Hãy chọn ngày hoặc chi nhánh khác trong lịch đang mở bán.</p>
    </div>
@elseif($context === 'cinema')
    <div class="space-y-5">@foreach($showtimes->groupBy(fn ($item) => $item['movie']->id) as $entries)<x-customer.showtimes.movie-card :entries="$entries" />@endforeach</div>
@else
    <div class="space-y-6">
        @foreach($showtimes->groupBy(fn ($item) => $item['cinema']->id) as $cinemaEntries)
            @php($branch = $cinemaEntries->first()['cinema'])
            <section class="space-y-4" data-cinema-code="{{ $branch->code }}">
                <div><h3 class="text-xl font-extrabold app-text">{{ $branch->name }}</h3><p class="text-sm app-muted">{{ $branch->address }}</p></div>
                @foreach($cinemaEntries->groupBy(fn ($item) => $item['movie']->id) as $entries)<x-customer.showtimes.movie-card :entries="$entries" />@endforeach
            </section>
        @endforeach
    </div>
@endif
