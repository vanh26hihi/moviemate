@extends('layouts.admin')
@section('title', 'Thêm suất chiếu - Quản trị MovieMate')
@section('page-title', 'Thêm suất chiếu')
@section('suppress-global-validation-summary', '1')

@section('content')
<section class="md:col-span-2 rounded-2xl border app-border app-card-soft p-5">

    <div class="mb-5">

        <p class="text-xs font-extrabold uppercase tracking-wider text-brand-start">
            Lịch chiếu
        </p>

        <h3 class="mt-1 text-lg font-extrabold app-heading">
            Chọn ngày và giờ chiếu
        </h3>

        <p class="mt-1 text-sm app-muted">
            Chọn thời điểm bắt đầu suất chiếu.
            Hệ thống sẽ kiểm tra trùng lịch trong cùng phòng.
        </p>

    </div>


    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

        <div>

            <label
                for="show-date"
                class="cinema-label"
            >
                Ngày chiếu *
            </label>

            <div class="relative">

                <i
                    class="ph ph-calendar-blank absolute left-4 top-1/2 -translate-y-1/2 app-muted"
                    aria-hidden="true"
                ></i>

                <input
                    id="show-date"
                    type="date"
                    name="show_date"
                    value="{{ old('show_date') }}"
                    min="{{ now()->format('Y-m-d') }}"
                    class="cinema-input pl-11"
                    required
                >

            </div>

            @error('show_date')

                <div class="mt-2 flex items-start gap-2 text-sm text-error">

                    <i
                        class="ph ph-warning-circle mt-0.5"
                        aria-hidden="true"
                    ></i>

                    <p>
                        {{ $message }}
                    </p>

                </div>

            @enderror

        </div>


        <div>

            <label
                for="show-time"
                class="cinema-label"
            >
                Giờ chiếu *
            </label>

            <div class="relative">

                <i
                    class="ph ph-clock absolute left-4 top-1/2 -translate-y-1/2 app-muted"
                    aria-hidden="true"
                ></i>

                <input
                    id="show-time"
                    type="time"
                    name="show_time"
                    value="{{ old('show_time') }}"
                    step="300"
                    class="cinema-input pl-11"
                    required
                >

            </div>

            @error('show_time')

                <div class="mt-2 flex items-start gap-2 text-sm text-error">

                    <i
                        class="ph ph-warning-circle mt-0.5"
                        aria-hidden="true"
                    ></i>

                    <p>
                        {{ $message }}
                    </p>

                </div>

            @enderror

        </div>

    </div>


    <div
        id="showtime-preview"
        class="mt-5 hidden rounded-xl border border-brand-start/20 bg-brand-start/5 p-4"
    >

        <div class="flex items-start gap-3">

            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-start/10 text-brand-start"
            >
                <i
                    class="ph ph-calendar-check"
                    aria-hidden="true"
                ></i>
            </div>

            <div>

                <p class="text-xs font-bold uppercase tracking-wider app-muted">
                    Thời gian đã chọn
                </p>

                <p
                    id="showtime-preview-value"
                    class="mt-1 font-extrabold app-text"
                ></p>

            </div>

        </div>

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
        
            const movieInput =
                document.getElementById('movie-select');
        
            const dateInput =
                document.getElementById('show-date');
        
            const timeInput =
                document.getElementById('show-time');
        
            const preview =
                document.getElementById('showtime-preview');
        
            const previewValue =
                document.getElementById('showtime-preview-value');
        
            const movieName =
                document.getElementById('schedule-movie-name');
        
            const durationValue =
                document.getElementById('schedule-duration');
        
            const endTimeValue =
                document.getElementById('schedule-end-time');
        
            const roomReadyValue =
                document.getElementById('schedule-room-ready');
        
            const warning =
                document.getElementById('schedule-warning');
        
            const warningMessage =
                document.getElementById('schedule-warning-message');
        
            const success =
                document.getElementById('schedule-success');
        
            const quickButtons =
                document.querySelectorAll('.showtime-quick-time');
        
            const cleaningBuffer =
                Number(@json($cleaningBufferMinutes ?? 0));
        
        
            function selectedMovie() {
        
                if (!movieInput) {
                    return null;
                }
        
                return movieInput.options[
                    movieInput.selectedIndex
                ] ?? null;
            }
        
        
            function movieDuration() {
        
                const option = selectedMovie();
        
                if (!option) {
                    return 0;
                }
        
                return Number(
                    option.dataset.duration || 0
                );
            }
        
        
            function updateMovieInformation() {
        
                const option = selectedMovie();
        
                if (!option || !option.value) {
        
                    if (movieName) {
                        movieName.textContent =
                            'Chưa chọn phim';
                    }
        
                    if (durationValue) {
                        durationValue.textContent =
                            'Chưa xác định';
                    }
        
                    return;
                }
        
                if (movieName) {
                    movieName.textContent =
                        option.dataset.title
                        || option.textContent.trim();
                }
        
                const duration =
                    movieDuration();
        
                if (durationValue) {
        
                    durationValue.textContent =
                        duration > 0
                            ? duration + ' phút'
                            : 'Chưa cập nhật';
                }
            }
        
        
            function formatDate(date) {
        
                const parts =
                    date.split('-');
        
                if (parts.length !== 3) {
                    return date;
                }
        
                return parts[2]
                    + '/'
                    + parts[1]
                    + '/'
                    + parts[0];
            }
        
        
            function formatTime(date) {
        
                const hours =
                    String(
                        date.getHours()
                    ).padStart(2, '0');
        
                const minutes =
                    String(
                        date.getMinutes()
                    ).padStart(2, '0');
        
                return hours + ':' + minutes;
            }
        
        
            function buildStartDate() {
        
                if (
                    !dateInput?.value
                    || !timeInput?.value
                ) {
                    return null;
                }
        
                const value =
                    dateInput.value
                    + 'T'
                    + timeInput.value
                    + ':00';
        
                const start =
                    new Date(value);
        
                if (
                    Number.isNaN(
                        start.getTime()
                    )
                ) {
                    return null;
                }
        
                return start;
            }
        
        
            function updateEndTime() {
        
                const start =
                    buildStartDate();
        
                const duration =
                    movieDuration();
        
                if (
                    !start
                    || duration <= 0
                ) {
        
                    if (endTimeValue) {
                        endTimeValue.textContent =
                            'Chưa xác định';
                    }
        
                    if (roomReadyValue) {
                        roomReadyValue.textContent =
                            'Chưa xác định';
                    }
        
                    return;
                }
        
                const movieEnd =
                    new Date(
                        start.getTime()
                        + duration * 60000
                    );
        
                const roomReady =
                    new Date(
                        movieEnd.getTime()
                        + cleaningBuffer * 60000
                    );
        
                if (endTimeValue) {
                    endTimeValue.textContent =
                        formatTime(movieEnd);
                }
        
                if (roomReadyValue) {
        
                    roomReadyValue.textContent =
                        formatTime(roomReady)
                        + (
                            cleaningBuffer > 0
                                ? ' (+' + cleaningBuffer + ' phút)'
                                : ''
                        );
                }
            }
        
        
            function hideMessages() {
        
                warning?.classList.add(
                    'hidden'
                );
        
                success?.classList.add(
                    'hidden'
                );
            }
        
        
            function showWarning(message) {
        
                success?.classList.add(
                    'hidden'
                );
        
                if (warningMessage) {
                    warningMessage.textContent =
                        message;
                }
        
                warning?.classList.remove(
                    'hidden'
                );
            }
        
        
            function showSuccess() {
        
                warning?.classList.add(
                    'hidden'
                );
        
                success?.classList.remove(
                    'hidden'
                );
            }
        
        
            function validateSchedule() {
        
                hideMessages();
        
                if (
                    !dateInput?.value
                    || !timeInput?.value
                ) {
                    return;
                }
        
                const start =
                    buildStartDate();
        
                if (!start) {
        
                    showWarning(
                        'Ngày hoặc giờ chiếu không hợp lệ.'
                    );
        
                    return;
                }
        
                const now =
                    new Date();
        
                if (
                    start.getTime()
                    <= now.getTime()
                ) {
        
                    showWarning(
                        'Thời gian bắt đầu suất chiếu phải nằm trong tương lai.'
                    );
        
                    return;
                }
        
                const duration =
                    movieDuration();
        
                if (
                    movieInput?.value
                    && duration <= 0
                ) {
        
                    showWarning(
                        'Phim đã chọn chưa có thời lượng hợp lệ.'
                    );
        
                    return;
                }
        
                showSuccess();
            }
        
        
            function updatePreview() {
        
                if (
                    !dateInput
                    || !timeInput
                    || !preview
                    || !previewValue
                ) {
                    return;
                }
        
                const date =
                    dateInput.value;
        
                const time =
                    timeInput.value;
        
                if (
                    !date
                    || !time
                ) {
        
                    preview.classList.add(
                        'hidden'
                    );
        
                    return;
                }
        
                previewValue.textContent =
                    time
                    + ' - '
                    + formatDate(date);
        
                preview.classList.remove(
                    'hidden'
                );
            }
        
        
            function updateAll() {
        
                updateMovieInformation();
        
                updatePreview();
        
                updateEndTime();
        
                validateSchedule();
            }
        
        
            quickButtons.forEach(
                function (button) {
        
                    button.addEventListener(
                        'click',
                        function () {
        
                            if (!timeInput) {
                                return;
                            }
        
                            timeInput.value =
                                button.dataset.time || '';
        
                            quickButtons.forEach(
                                function (item) {
        
                                    item.classList.remove(
                                        'border-brand-start',
                                        'text-brand-start'
                                    );
                                }
                            );
        
                            button.classList.add(
                                'border-brand-start',
                                'text-brand-start'
                            );
        
                            updateAll();
                        }
                    );
                }
            );
        
        
            movieInput?.addEventListener(
                'change',
                updateAll
            );
        
            dateInput?.addEventListener(
                'change',
                updateAll
            );
        
            timeInput?.addEventListener(
                'change',
                updateAll
            );
        
            timeInput?.addEventListener(
                'input',
                updateAll
            );
        
        
            updateAll();
        
        });
        </script>
</section>
</div>
@endsection
