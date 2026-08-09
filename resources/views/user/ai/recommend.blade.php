@extends('layouts.user')

@section('title', 'AI gợi ý phim - MovieMate')

@php
    $selectedGenres = collect(old('genres', $preferences['genres'] ?? []))->map(fn ($genre) => (string) $genre)->all();
    $selectedMood = old('mood', $preferences['mood'] ?? 'chill');
    $selectedTime = old('preferred_time', $preferences['preferred_time'] ?? 'tonight');
    $selectedCompanion = old('companion', $preferences['companion'] ?? 'friends');
    $locationValue = old('location', $preferences['location'] ?? '');

    $moods = [
        'happy' => ['label' => 'Vui vẻ', 'icon' => 'ph-smiley'],
        'sad' => ['label' => 'Muốn chữa lành', 'icon' => 'ph-cloud-rain'],
        'stress' => ['label' => 'Căng thẳng', 'icon' => 'ph-lightning'],
        'chill' => ['label' => 'Muốn thư giãn', 'icon' => 'ph-coffee'],
        'excited' => ['label' => 'Muốn bùng nổ', 'icon' => 'ph-fire'],
        'romantic' => ['label' => 'Lãng mạn', 'icon' => 'ph-heart'],
    ];

    $timeOptions = [
        'tonight' => 'Tối nay',
        'tomorrow' => 'Ngày mai',
        'weekend' => 'Cuối tuần',
        'after_21' => 'Suất sau 21:00',
        'morning' => 'Buổi sáng',
        'afternoon' => 'Buổi chiều',
    ];

    $companions = [
        'alone' => 'Một mình',
        'couple' => 'Người yêu / vợ chồng',
        'friends' => 'Bạn bè',
        'family' => 'Gia đình có trẻ em',
    ];
@endphp

@section('content')
<section class="relative overflow-hidden py-14 md:py-20">
    <div class="absolute inset-0 bg-gradient-to-br from-dark-main via-dark-main to-[#111827]"></div>
    <div class="absolute inset-0 opacity-40 bg-[radial-gradient(circle_at_18%_18%,rgba(124,58,237,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(37,99,235,0.22),transparent_30%),radial-gradient(circle_at_52%_100%,rgba(255,61,87,0.14),transparent_34%)]"></div>

    <div class="relative max-w-4xl mx-auto px-4 text-center">
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-ai-start/10 border border-ai-start/30 mb-6">
            <i class="ph-fill ph-magic-wand text-ai-start"></i>
            <span class="text-sm font-medium text-ai-start">Trí tuệ nhân tạo MovieMate</span>
        </div>
        <h1 class="hero-title text-4xl md:text-6xl font-extrabold app-text mb-6">
            AI gợi ý phim <span class="text-transparent bg-clip-text bg-gradient-to-r from-ai-start to-ai-end">dành riêng cho bạn</span>
        </h1>
        <p class="text-lg app-muted mb-0 max-w-2xl mx-auto leading-relaxed">
            Nhập sở thích, tâm trạng và khu vực mong muốn. MovieMate chỉ gợi ý phim đang có trong hệ thống và còn suất chiếu hợp lệ.
        </p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">
        <div class="lg:col-span-5">
            <div class="app-card border app-border rounded-3xl p-6 sm:p-8 shadow-2xl shadow-black/25">
                @if($errors->any())
                    <div class="mb-6 rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('user.ai.recommend.submit') }}" method="POST" class="space-y-6">
                    @csrf

                    <div>
                        <label class="block text-sm font-bold app-text mb-3">Bạn đang cảm thấy thế nào?</label>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach($moods as $value => $mood)
                                <label class="cursor-pointer">
                                    <input type="radio" name="mood" class="peer sr-only" value="{{ $value }}" @checked($selectedMood === $value)>
                                    <span class="min-h-12 px-4 py-3 app-input border app-border rounded-2xl app-muted hover:border-ai-start/50 peer-checked:bg-ai-start/10 peer-checked:border-ai-start peer-checked:text-ai-start transition-all text-sm font-semibold flex items-center gap-2">
                                        <i class="ph {{ $mood['icon'] }}"></i> {{ $mood['label'] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold app-text mb-3">Thể loại bạn muốn xem?</label>
                        <div class="flex flex-wrap gap-3">
                            @forelse($genres as $genre)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="genres[]" class="peer sr-only" value="{{ $genre->name }}" @checked(in_array($genre->name, $selectedGenres, true))>
                                    <span class="inline-flex px-4 py-2.5 app-input border app-border rounded-xl app-muted hover:border-ai-start/50 peer-checked:bg-ai-start/10 peer-checked:border-ai-start peer-checked:text-ai-start transition-all text-sm font-semibold">
                                        {{ $genre->name }}
                                    </span>
                                </label>
                            @empty
                                <p class="app-muted text-sm">Chưa có thể loại trong hệ thống.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="companion" class="block text-sm font-bold app-text mb-3">Bạn muốn xem với ai?</label>
                            <select id="companion" name="companion" class="w-full px-4 py-3 app-input border app-border rounded-2xl focus:outline-none focus:border-ai-start">
                                @foreach($companions as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedCompanion === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="preferred_time" class="block text-sm font-bold app-text mb-3">Thời gian muốn xem?</label>
                            <select id="preferred_time" name="preferred_time" class="w-full px-4 py-3 app-input border app-border rounded-2xl focus:outline-none focus:border-ai-start">
                                @foreach($timeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedTime === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="location" class="block text-sm font-bold app-text mb-3">Khu vực/rạp mong muốn?</label>
                        <input id="location" name="location" type="text" list="cinema-options" value="{{ $locationValue }}" class="w-full px-4 py-3 app-input border app-border rounded-2xl focus:outline-none focus:border-ai-start" placeholder="Ví dụ: Quận 1, Hà Nội, MovieMate Center">
                        <datalist id="cinema-options">
                            @foreach($cinemas as $cinema)
                                <option value="{{ $cinema->name }}">{{ $cinema->city }}</option>
                                <option value="{{ $cinema->city }}">{{ $cinema->name }}</option>
                            @endforeach
                        </datalist>
                    </div>

                    <button type="submit" class="w-full py-4 bg-gradient-to-r from-ai-start to-ai-end text-white rounded-2xl font-bold text-lg hover:shadow-lg hover:shadow-ai-start/30 transition-all flex items-center justify-center gap-2">
                        <i class="ph-fill ph-magic-wand"></i> Tạo gợi ý ngay
                    </button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-7">
            <div class="mb-6 flex items-center gap-3">
                <span class="w-8 h-px bg-ai-start/60"></span>
                <h2 class="text-xl font-bold app-text uppercase tracking-wider">AI đề xuất cho bạn</h2>
                <span class="h-px bg-[var(--border-color)] flex-grow"></span>
            </div>

            @if($recommendationMeta && !empty($recommendationMeta['message']))
                <div class="mb-5 rounded-2xl border border-warning/30 bg-warning/10 text-warning px-4 py-3 text-sm">
                    {{ $recommendationMeta['message'] }}
                </div>
            @endif

            @if(is_null($recommendations))
                <div class="app-card border app-border rounded-3xl p-8 sm:p-10 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-ai-start/10 text-ai-start flex items-center justify-center mx-auto mb-5">
                        <i class="ph-fill ph-sparkle text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold app-text mb-3">Sẵn sàng tạo gợi ý</h3>
                    <p class="app-muted max-w-xl mx-auto leading-relaxed">
                        Kết quả sẽ được tạo từ danh sách phim đang chiếu và các suất chiếu còn hiệu lực trong database.
                    </p>
                </div>
            @elseif(empty($recommendations))
                <div class="app-card border app-border rounded-3xl p-8 sm:p-10 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-error/10 text-error flex items-center justify-center mx-auto mb-5">
                        <i class="ph ph-film-slate text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold app-text mb-3">Chưa có phim phù hợp</h3>
                    <p class="app-muted max-w-xl mx-auto leading-relaxed">
                        Hiện chưa có phim đang chiếu với suất chiếu còn hiệu lực. Hãy thử lại khi hệ thống có lịch chiếu mới.
                    </p>
                </div>
            @else
                <div class="space-y-5">
                    @foreach($recommendations as $index => $item)
                        @php
                            $poster = \App\Models\Movie::imageUrl($item['poster'] ?? null);
                            $detailUrl = route('user.movies.show', $item['slug']);
                            $showtimeUrl = $detailUrl . '#showtimes';
                        @endphp

                        <article class="{{ $index === 0 ? 'dark-surface bg-gradient-to-br from-[#1E1B4B] to-dark-main border-ai-start/30 shadow-ai-start/10' : 'app-card app-border' }} border rounded-3xl p-5 sm:p-6 relative overflow-hidden shadow-2xl">
                            @if($index === 0)
                                <i class="ph-fill ph-sparkle absolute top-6 right-6 text-4xl text-ai-start/45"></i>
                            @endif

                            <div class="flex flex-col md:flex-row gap-6">
                                <a href="{{ $detailUrl }}" class="w-full max-w-44 mx-auto md:mx-0 md:w-36 shrink-0">
                                    <div class="poster-frame rounded-3xl shadow-2xl shadow-black">
                                        @if($poster)
                                            <img src="{{ $poster }}" alt="{{ $item['title'] }}" loading="lazy">
                                        @else
                                            <div class="fallback-poster">
                                                <i class="ph-fill ph-film-slate"></i>
                                                <strong class="text-lg">MovieMate</strong>
                                                <span>{{ $item['title'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </a>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-3">
                                        <span class="inline-flex px-3 py-1 bg-ai-start/20 border border-ai-start/50 text-ai-start text-xs font-bold rounded-lg">
                                            Độ phù hợp: {{ $item['score'] }}%
                                        </span>
                                        @if($index === 0)
                                            <span class="inline-flex px-3 py-1 bg-brand-start/20 border border-brand-start/50 text-brand-start text-xs font-bold rounded-lg">
                                                Gợi ý tốt nhất
                                            </span>
                                        @endif
                                    </div>

                                    <h3 class="text-2xl sm:text-3xl font-bold app-text mb-2">
                                        <a href="{{ $detailUrl }}" class="hover:text-ai-start transition-colors">{{ $item['title'] }}</a>
                                    </h3>
                                    <p class="app-muted mb-4">
                                        {{ implode(', ', $item['genres']) ?: 'Chưa cập nhật thể loại' }}
                                        · {{ $item['duration'] ?? '--' }} phút
                                        · {{ $item['age_rating'] ?? 'P' }}
                                    </p>

                                    <div class="bg-dark-main/55 rounded-2xl p-4 mb-5 border border-dark-border">
                                        <p class="text-sm app-text leading-relaxed">
                                            <i class="ph-fill ph-robot text-ai-start mr-1"></i>
                                            <strong>Tại sao chọn phim này?</strong> {{ $item['reason'] }}
                                        </p>
                                    </div>

                                    <div class="mb-5">
                                        <p class="text-xs uppercase tracking-wider app-muted font-bold mb-2">Suất chiếu phù hợp</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach(array_slice($item['showtimes'], 0, 4) as $showtime)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 app-secondary border app-border rounded-xl text-xs app-text">
                                                    <i class="ph ph-clock text-ai-start"></i>
                                                    {{ \Carbon\Carbon::parse($showtime['date'])->format('d/m') }} {{ $showtime['time'] }}
                                                    <span class="app-muted">· {{ $showtime['cinema'] }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-3">
                                        <a href="{{ $showtimeUrl }}" class="px-5 py-3 bg-gradient-to-r from-brand-start to-brand-end text-white rounded-2xl font-bold hover:shadow-lg hover:shadow-brand-start/30 transition-all">
                                            Đặt vé ngay
                                        </a>
                                        <a href="{{ $detailUrl }}" class="px-5 py-3 app-card border app-border app-text rounded-2xl font-bold hover:border-ai-start transition-colors">
                                            Xem chi tiết
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
