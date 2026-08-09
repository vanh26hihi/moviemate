@extends('layouts.admin')

@section('title', 'Thêm suất chiếu - MovieMate Admin')
@section('page-title', 'Thêm suất chiếu')

@section('content')

<form
    method="POST"
    action="{{ route('admin.showtimes.store') }}"
    class="space-y-6"
>
    @csrf

    {{-- CHỌN PHIM --}}
    <section class="admin-detail-card">

        <div class="mb-5">
            <p class="text-xs font-extrabold uppercase tracking-wider text-brand-start">
                Bước 1
            </p>

            <h2 class="mt-1 text-xl font-extrabold app-heading">
                Chọn phim
            </h2>

            <p class="mt-1 text-sm app-text-muted">
                Chọn phim cần tạo suất chiếu.
            </p>
        </div>

        <div class="relative mb-5">
            <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 app-text-muted"></i>

            <input
                type="text"
                id="movie-search"
                class="cinema-input pl-11"
                placeholder="Tìm kiếm phim..."
                autocomplete="off"
            >
        </div>

        <input
            type="hidden"
            name="movie_id"
            id="movie-id"
            value="{{ old('movie_id') }}"
        >

        @error('movie_id')
            <p class="mb-4 text-sm text-error">
                {{ $message }}
            </p>
        @enderror

        @if($movies->isEmpty())

            <div class="rounded-2xl border app-border app-card-soft p-8 text-center">

                <i class="ph-fill ph-film-slate text-4xl app-text-muted"></i>

                <p class="mt-3 font-bold app-text">
                    Không có phim khả dụng
                </p>

                <p class="mt-1 text-sm app-text-muted">
                    Hiện chưa có phim phù hợp để tạo suất chiếu.
                </p>

            </div>

        @else

            <div
                id="movie-list"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >

                @foreach($movies as $movie)

                    <button
                        type="button"
                        class="movie-option group rounded-2xl border p-3 text-left transition
                            {{ old('movie_id') == $movie->id
                                ? 'border-brand-start bg-brand-start/10'
                                : 'app-border app-card-soft hover:border-brand-start'
                            }}"
                        data-movie-id="{{ $movie->id }}"
                        data-movie-title="{{ strtolower($movie->title) }}"
                    >

                        <div class="flex gap-4">

                            <div class="h-28 w-20 shrink-0 overflow-hidden rounded-xl bg-slate-950">

                                @if($movie->poster_url)

                                    <img
                                        src="{{ $movie->poster_url }}"
                                        alt="{{ $movie->title }}"
                                        class="h-full w-full object-cover"
                                    >

                                @else

                                    <div class="admin-media-fallback h-full w-full text-xs">
                                        MM
                                    </div>

                                @endif

                            </div>

                            <div class="min-w-0 flex-1">

                                <h3 class="line-clamp-2 font-extrabold app-heading">
                                    {{ $movie->title }}
                                </h3>

                                <div class="mt-2 space-y-1 text-xs app-text-muted">

                                    @if($movie->duration)
                                        <p>
                                            <i class="ph ph-clock"></i>
                                            {{ $movie->duration }} phút
                                        </p>
                                    @endif

                                    @if($movie->country)
                                        <p>
                                            <i class="ph ph-globe"></i>
                                            {{ $movie->country }}
                                        </p>
                                    @endif

                                    <p>
                                        <i class="ph ph-film-strip"></i>
                                        {{ $movie->status_label ?? $movie->status }}
                                    </p>

                                </div>

                                <div class="movie-selected-label mt-3 hidden text-sm font-extrabold text-brand-start">
                                    <i class="ph-fill ph-check-circle"></i>
                                    Đã chọn
                                </div>

                            </div>

                        </div>

                    </button>

                @endforeach

            </div>

            <div
                id="movie-empty-search"
                class="hidden rounded-2xl border app-border app-card-soft p-6 text-center app-text-muted"
            >
                Không tìm thấy phim phù hợp.
            </div>

        @endif

    </section>


    {{-- THÔNG TIN SUẤT CHIẾU --}}
    <section class="admin-detail-card">

        <div class="mb-5">
            <p class="text-xs font-extrabold uppercase tracking-wider text-brand-start">
                Bước 2
            </p>

            <h2 class="mt-1 text-xl font-extrabold app-heading">
                Thông tin suất chiếu
            </h2>
        </div>


        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

            {{-- RẠP --}}
            <div>
                <label class="cinema-label">
                    Rạp
                </label>

                <input
                    type="text"
                    class="cinema-input"
                    value="{{ $cinema?->name ?? 'Chưa xác định' }}"
                    disabled
                >

                @if($cinema)
                    <input
                        type="hidden"
                        name="cinema_id"
                        value="{{ $cinema->id }}"
                    >
                @endif
            </div>


            {{-- PHÒNG --}}
            <div>
                <label class="cinema-label">
                    Phòng *
                </label>

                <select
                    name="room_id"
                    class="cinema-input"
                >
                    <option value="">
                        -- Chọn phòng --
                    </option>

                    @foreach($rooms as $room)

                        <option
                            value="{{ $room->id }}"
                            {{ old('room_id') == $room->id
                                ? 'selected'
                                : ''
                            }}
                        >
                            {{ $room->name ?? $room->code }}

                            @if($room->cinema)
                                - {{ $room->cinema->name }}
                            @endif
                        </option>

                    @endforeach

                </select>

                @error('room_id')
                    <p class="mt-2 text-sm text-error">
                        {{ $message }}
                    </p>
                @enderror
            </div>


            {{-- NGÀY --}}
            <div>
                <label class="cinema-label">
                    Ngày chiếu *
                </label>

                <input
                    type="date"
                    name="show_date"
                    value="{{ old('show_date') }}"
                    class="cinema-input"
                >

                @error('show_date')
                    <p class="mt-2 text-sm text-error">
                        {{ $message }}
                    </p>
                @enderror
            </div>


            {{-- GIỜ --}}
            <div>
                <label class="cinema-label">
                    Giờ chiếu *
                </label>

                <input
                    type="time"
                    name="show_time"
                    value="{{ old('show_time') }}"
                    class="cinema-input"
                >

                @error('show_time')
                    <p class="mt-2 text-sm text-error">
                        {{ $message }}
                    </p>
                @enderror
            </div>


            {{-- GIÁ THƯỜNG --}}
            <div>
                <label class="cinema-label">
                    Giá thường (VND) *
                </label>

                <input
                    type="number"
                    name="price"
                    step="1000"
                    min="0"
                    value="{{ old('price') }}"
                    class="cinema-input"
                >

                @error('price')
                    <p class="mt-2 text-sm text-error">
                        {{ $message }}
                    </p>
                @enderror
            </div>


            {{-- GIÁ VIP --}}
            <div>
                <label class="cinema-label">
                    Giá VIP (VND)
                </label>

                <input
                    type="number"
                    name="vip_price"
                    step="1000"
                    min="0"
                    value="{{ old('vip_price') }}"
                    class="cinema-input"
                >

                @error('vip_price')
                    <p class="mt-2 text-sm text-error">
                        {{ $message }}
                    </p>
                @enderror
            </div>


            {{-- TRẠNG THÁI --}}
            <div>
                <label class="cinema-label">
                    Trạng thái *
                </label>

                <select
                    name="status"
                    class="cinema-input"
                >
                    <option
                        value="active"
                        {{ old('status', 'active') === 'active'
                            ? 'selected'
                            : ''
                        }}
                    >
                        Đang hoạt động
                    </option>

                    <option
                        value="cancelled"
                        {{ old('status') === 'cancelled'
                            ? 'selected'
                            : ''
                        }}
                    >
                        Đã hủy
                    </option>

                    <option
                        value="finished"
                        {{ old('status') === 'finished'
                            ? 'selected'
                            : ''
                        }}
                    >
                        Đã chiếu xong
                    </option>
                </select>

                @error('status')
                    <p class="mt-2 text-sm text-error">
                        {{ $message }}
                    </p>
                @enderror
            </div>

        </div>

    </section>


    <div class="flex flex-col justify-end gap-3 pt-2 sm:flex-row">

        <a
            href="{{ route('admin.showtimes.index') }}"
            class="btn-secondary"
        >
            Hủy
        </a>

        <button
            type="submit"
            class="btn-primary"
        >
            <i class="ph-bold ph-check"></i>
            Lưu suất chiếu
        </button>

    </div>

</form>

@endsection


@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    const movieInput = document.getElementById('movie-id');
    const movieSearch = document.getElementById('movie-search');
    const movieCards = document.querySelectorAll('.movie-option');
    const emptySearch = document.getElementById('movie-empty-search');

    function selectMovie(card) {

        movieCards.forEach(function (item) {

            item.classList.remove(
                'border-brand-start',
                'bg-brand-start/10'
            );

            item.classList.add('app-border');

            const label = item.querySelector(
                '.movie-selected-label'
            );

            if (label) {
                label.classList.add('hidden');
            }

        });


        card.classList.remove('app-border');

        card.classList.add(
            'border-brand-start',
            'bg-brand-start/10'
        );


        const selectedLabel = card.querySelector(
            '.movie-selected-label'
        );

        if (selectedLabel) {
            selectedLabel.classList.remove('hidden');
        }


        movieInput.value = card.dataset.movieId;
    }


    movieCards.forEach(function (card) {

        card.addEventListener('click', function () {
            selectMovie(card);
        });


        if (
            movieInput &&
            movieInput.value === card.dataset.movieId
        ) {
            selectMovie(card);
        }

    });


    if (movieSearch) {

        movieSearch.addEventListener('input', function () {

            const keyword = this.value
                .trim()
                .toLowerCase();

            let visible = 0;


            movieCards.forEach(function (card) {

                const title =
                    card.dataset.movieTitle || '';

                const matched =
                    title.includes(keyword);

                card.classList.toggle(
                    'hidden',
                    !matched
                );

                if (matched) {
                    visible++;
                }

            });


            if (emptySearch) {
                emptySearch.classList.toggle(
                    'hidden',
                    visible !== 0
                );
            }

        });

    }

});
</script>

@endpush