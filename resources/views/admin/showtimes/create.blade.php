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

</section>
</div>
@endsection
