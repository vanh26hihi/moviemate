@extends('layouts.app')

@section('title', 'Danh sách phim - MovieMate')

@section('content')
<section class="cinema-surface relative overflow-hidden py-10 md:py-14">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.25em] mb-3">MovieMate Cinema</p>
        <h1 class="hero-title text-4xl md:text-5xl font-extrabold app-text mb-4">{{ $pageTitle }}</h1>
        <p class="app-muted max-w-2xl">Tìm phim đang chiếu, sắp chiếu và chọn suất phù hợp cho buổi xem tiếp theo.</p>

        <div class="mt-8 flex flex-wrap gap-2">
            @foreach([
                '' => 'Tất cả',
                'now_showing' => 'Đang chiếu',
                'coming_soon' => 'Sắp chiếu',
            ] as $value => $label)
                <a href="{{ route('user.movies.index', array_filter(['status' => $value])) }}" class="px-4 py-2 rounded-full border text-sm font-extrabold transition-colors {{ request('status', '') === $value ? 'bg-brand-start border-brand-start text-white' : 'app-card app-border app-text hover:border-brand-start hover:text-brand-start' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('user.movies.index') }}" class="mt-6 cinema-card p-3 grid grid-cols-1 md:grid-cols-[1fr_180px_180px_170px_auto] gap-3">
            <label class="flex items-center gap-3 px-4 app-input border app-border rounded-2xl">
                <i class="ph ph-magnifying-glass app-muted text-xl"></i>
                <input type="text" name="keyword" placeholder="Tìm kiếm tên phim..." value="{{ request('keyword', request('search')) }}" class="w-full bg-transparent app-text placeholder:text-text-sub/70 focus:outline-none py-3">
            </label>

            <select name="genre_id" class="cinema-input">
                <option value="">Tất cả thể loại</option>
                @foreach(\App\Models\Genre::orderBy('name')->get() as $genre)
                    <option value="{{ $genre->id }}" {{ request('genre_id') == $genre->id ? 'selected' : '' }}>
                        {{ $genre->name }}
                    </option>
                @endforeach
            </select>

            <select name="country" class="cinema-input">
                <option value="">Tất cả quốc gia</option>
                @foreach(($countries ?? collect()) as $country)
                    <option value="{{ $country }}" {{ request('country') === $country ? 'selected' : '' }}>{{ $country }}</option>
                @endforeach
            </select>

            <select name="status" class="cinema-input">
                <option value="">Tất cả trạng thái</option>
                <option value="now_showing" {{ request('status') === 'now_showing' ? 'selected' : '' }}>Đang chiếu</option>
                <option value="coming_soon" {{ request('status') === 'coming_soon' ? 'selected' : '' }}>Sắp chiếu</option>
            </select>

            <button type="submit" class="btn-primary !rounded-2xl">
                <i class="ph ph-sliders-horizontal"></i>
                Lọc
            </button>
        </form>
    </div>
</section>

<section id="showtimes" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-extrabold app-text">{{ $pageTitle }}</h2>
            <p class="app-muted mt-2">{{ $movies->total() }} phim phù hợp với bộ lọc hiện tại.</p>
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
                <h3 class="text-xl font-extrabold app-text mb-2">Không có phim nào</h3>
                <p class="app-muted">Hãy thử đổi từ khóa tìm kiếm hoặc bộ lọc thể loại.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $movies->links() }}
    </div>
</section>
@endsection

