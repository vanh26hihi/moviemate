@extends('layouts.app')

@section('title', 'Danh sách phim - MovieMate')

@section('content')
<section class="cinema-surface relative overflow-hidden py-10 md:py-14">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.25em] mb-3">
            MovieMate Cinema
        </p>

        <h1 class="hero-title text-4xl md:text-5xl font-extrabold app-text mb-4">
            {{ $pageTitle }}
        </h1>

        <p class="app-muted max-w-2xl">
            Tìm kiếm phim đang chiếu, phim sắp chiếu và lựa chọn suất chiếu phù hợp.
        </p>

        {{-- Lọc nhanh theo trạng thái --}}
        <div class="mt-8 flex flex-wrap gap-2">
            @foreach([
                '' => 'Tất cả',
                'now_showing' => 'Đang chiếu',
                'coming_soon' => 'Sắp chiếu',
            ] as $value => $label)

                @php
                    $params = request()->except(['status', 'page']);

                    if ($value !== '') {
                        $params['status'] = $value;
                    }
                @endphp

                <a
                    href="{{ route('user.movies.index', $params) }}"
                    class="px-4 py-2 rounded-full border text-sm font-extrabold transition-colors
                    {{ request('status', '') === $value
                        ? 'bg-brand-start border-brand-start text-white'
                        : 'app-card app-border app-text hover:border-brand-start hover:text-brand-start' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Form tìm kiếm và lọc --}}
        <form
            method="GET"
            action="{{ route('user.movies.index') }}"
            class="mt-6 cinema-card p-3 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-[1fr_170px_170px_170px_180px_auto] gap-3"
        >
            <label class="flex items-center gap-3 px-4 app-input border app-border rounded-2xl">
                <i class="ph ph-magnifying-glass app-muted text-xl"></i>

                <input
                    type="text"
                    name="search"
                    placeholder="Tìm kiếm tên phim..."
                    value="{{ request('search', request('keyword')) }}"
                    class="w-full bg-transparent app-text placeholder:text-text-sub/70 focus:outline-none py-3"
                >
            </label>
            <select name="duration" class="cinema-input">
                <option value="">Tất cả thời lượng</option>
            
                <option value="short" {{ request('duration') === 'short' ? 'selected' : '' }}>
                    Dưới 90 phút
                </option>
            
                <option value="medium" {{ request('duration') === 'medium' ? 'selected' : '' }}>
                    90 - 120 phút
                </option>
            
                <option value="long" {{ request('duration') === 'long' ? 'selected' : '' }}>
                    Trên 120 phút
                </option>
            </select>
            <select name="genre" class="cinema-input">
                <option value="">Tất cả thể loại</option>

                @foreach($genres as $genre)
                    <option
                        value="{{ $genre->id }}"
                        {{ (string) request('genre', request('genre_id')) === (string) $genre->id ? 'selected' : '' }}
                    >
                        {{ $genre->name }}
                    </option>
                @endforeach
            </select>

            <select name="country" class="cinema-input">
                <option value="">Tất cả quốc gia</option>

                @foreach($countries as $country)
                    <option
                        value="{{ $country }}"
                        {{ request('country') === $country ? 'selected' : '' }}
                    >
                        {{ $country }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="cinema-input">
                <option value="">Tất cả trạng thái</option>

                <option
                    value="now_showing"
                    {{ request('status') === 'now_showing' ? 'selected' : '' }}
                >
                    Đang chiếu
                </option>

                <option
                    value="coming_soon"
                    {{ request('status') === 'coming_soon' ? 'selected' : '' }}
                >
                    Sắp chiếu
                </option>
            </select>
            <select name="age_rating" class="cinema-input">
                <option value="">Tất cả độ tuổi</option>
            
                @foreach($ageRatings as $age)
                    <option
                        value="{{ $age }}"
                        {{ request('age_rating') === $age ? 'selected' : '' }}
                    >
                        {{ $age }}
                    </option>
                @endforeach
            </select>
            <select name="sort" class="cinema-input">
                <option value="latest" {{ request('sort', 'latest') === 'latest' ? 'selected' : '' }}>
                    Mới nhất
                </option>

                <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>
                    Tên A - Z
                </option>

                <option value="rating" {{ request('sort') === 'rating' ? 'selected' : '' }}>
                    Điểm đánh giá cao
                </option>

                <option value="release_date" {{ request('sort') === 'release_date' ? 'selected' : '' }}>
                    Khởi chiếu gần nhất
                </option>

                <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>
                    Cũ nhất
                </option>
            </select>

            <button type="submit" class="btn-primary !rounded-2xl">
                <i class="ph ph-sliders-horizontal"></i>
                Lọc
            </button>
        </form>
        @if(request()->hasAny([
    'search',
    'keyword',
    'genre',
    'genre_id',
    'country',
    'status',
    'sort',
    'age_rating'
]))

        @if(request()->hasAny([
            'search',
            'keyword',
            'genre',
            'genre_id',
            'country',
            'status',
            'sort'
        ]))
            <div class="mt-4">
                <a
                    href="{{ route('user.movies.index') }}"
                    class="inline-flex items-center gap-2 text-sm font-bold app-muted hover:text-brand-start"
                >
                    <i class="ph ph-arrow-counter-clockwise"></i>
                    Đặt lại bộ lọc
                </a>
            </div>
        @endif
    </div>
</section>

<section
    id="movies"
    class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12"
>
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-extrabold app-text">
                {{ $pageTitle }}
            </h2>

            @if(!empty($search))
                <p class="app-muted mt-2">
                    Kết quả tìm kiếm cho:
                    <strong class="app-text">“{{ $search }}”</strong>
                </p>
            @endif

            <p class="app-muted mt-1">
                Có {{ $movies->total() }} phim phù hợp.
                @if($movies->total() > 0)
                    Đang hiển thị từ {{ $movies->firstItem() }}
                    đến {{ $movies->lastItem() }}.
                @endif
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
        @forelse($movies as $movie)
            @include('user.movies._card', ['movie' => $movie])
        @empty
            <div class="col-span-full cinema-card p-10 text-center">
                <div class="w-16 h-16 rounded-2xl bg-brand-start/10 text-brand-start flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-film-slate text-3xl"></i>
                </div>

                <h3 class="text-xl font-extrabold app-text mb-2">
                    Không tìm thấy phim phù hợp
                </h3>

                @if(!empty($search))
                    <p class="app-muted">
                        Không tìm thấy kết quả cho từ khóa
                        “{{ $search }}”.
                    </p>
                @else
                    <p class="app-muted">
                        Hãy thử thay đổi thể loại, quốc gia hoặc trạng thái phim.
                    </p>
                @endif

                <a
                    href="{{ route('user.movies.index') }}"
                    class="btn-primary inline-flex mt-5"
                >
                    Xem tất cả phim
                </a>
            </div>
        @endforelse
    </div>

    @if($movies->hasPages())
        <div class="mt-10">
            {{ $movies->onEachSide(1)->links() }}
        </div>
    @endif
</section>
@endsection