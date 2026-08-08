@php
    $initialCells = old(
        'layout.cells',
        $template->exists
            ? $template->cells->map(fn ($cell) => $cell->only([
                'x_position', 'y_position', 'cell_type', 'seat_type', 'seat_label', 'pair_key',
            ]))->values()->all()
            : [],
    );
    $initial = [
        'rows' => (int) old('layout.rows', $template->rows ?: 8),
        'columns' => (int) old('layout.columns', $template->columns ?: 12),
        'screen_position' => old('layout.screen_position', $template->screen_position ?: 'top'),
        'cells' => $initialCells,
    ];
    $tools = [
        'normal' => ['label' => 'Ghế thường', 'icon' => 'ph-armchair'],
        'vip' => ['label' => 'VIP', 'icon' => 'ph-star'],
        'couple' => ['label' => 'Ghế đôi', 'icon' => 'ph-heart'],
        'aisle' => ['label' => 'Lối đi', 'icon' => 'ph-arrows-down-up'],
        'empty' => ['label' => 'Xóa ô', 'icon' => 'ph-eraser'],
    ];
@endphp

<form method="POST" action="{{ $action }}" data-layout-template-form data-submit-once class="space-y-6">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    <section class="app-card rounded-3xl border app-border p-5 sm:p-6" aria-labelledby="template-metadata-title">
        <div class="mb-5">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Thông tin mẫu</p>
            <h2 id="template-metadata-title" class="mt-2 text-xl font-extrabold app-text">Thông tin nhận diện và phạm vi áp dụng</h2>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <label class="block min-w-0">
                <span class="cinema-label">Mã mẫu <span class="text-error" aria-hidden="true">*</span></span>
                <input class="cinema-input min-h-12 uppercase" name="code" value="{{ old('code', $template->code) }}" required maxlength="32" autocomplete="off" placeholder="STANDARD_100" aria-describedby="template-code-help @error('code') template-code-error @enderror">
                <span id="template-code-help" class="mt-2 block text-xs leading-relaxed app-muted">Mã dùng để nhận diện mẫu trong hệ thống và không được trùng.</span>
                @error('code')<span id="template-code-error" class="mt-2 block text-sm font-semibold text-error" role="alert">{{ $message }}</span>@enderror
            </label>

            <label class="block min-w-0">
                <span class="cinema-label">Tên mẫu <span class="text-error" aria-hidden="true">*</span></span>
                <input class="cinema-input min-h-12" name="name" value="{{ old('name', $template->name) }}" required minlength="5" maxlength="255" placeholder="Ví dụ: Phòng tiêu chuẩn 100 ghế" @error('name') aria-describedby="template-name-error" @enderror>
                @error('name')<span id="template-name-error" class="mt-2 block text-sm font-semibold text-error" role="alert">{{ $message }}</span>@enderror
            </label>

            <label class="block min-w-0">
                <span class="cinema-label">Loại phòng</span>
                <select class="cinema-input min-h-12" name="room_type" @error('room_type') aria-describedby="template-room-type-error" @enderror>
                    <option value="">Mọi loại phòng</option>
                    @foreach($roomTypes as $type)
                        <option value="{{ $type->code }}" @selected(old('room_type', $template->room_type) === $type->code)>
                            {{ $type->name }}{{ $type->is_active ? '' : ' (đã lưu trữ)' }}
                        </option>
                    @endforeach
                </select>
                <span class="mt-2 block text-xs leading-relaxed app-muted">Danh sách lấy từ Loại phòng đang được quản lý trong hệ thống.</span>
                @error('room_type')<span id="template-room-type-error" class="mt-2 block text-sm font-semibold text-error" role="alert">{{ $message }}</span>@enderror
            </label>

            <label class="block min-w-0">
                <span class="cinema-label">Mô tả</span>
                <textarea class="cinema-input min-h-28 resize-y" name="description" maxlength="2000" rows="4" placeholder="Mô tả ngắn về mục đích sử dụng mẫu">{{ old('description', $template->description) }}</textarea>
                @error('description')<span class="mt-2 block text-sm font-semibold text-error" role="alert">{{ $message }}</span>@enderror
            </label>
        </div>
    </section>

    <section class="app-card overflow-hidden rounded-3xl border app-border" aria-labelledby="seat-layout-editor-title">
        <div class="border-b app-border p-5 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Thiết kế sơ đồ ghế</p>
            <h2 id="seat-layout-editor-title" class="mt-2 text-xl font-extrabold app-text">Bố trí từng vị trí trong phòng chiếu</h2>
            <p id="seat-layout-editor-help" class="mt-2 max-w-3xl text-sm leading-relaxed app-muted">Chọn một công cụ rồi nhấn vào ô trên sơ đồ để bố trí ghế. Ghế đôi sử dụng hai ô liền kề và mã ghế được tạo tự động theo hàng.</p>
        </div>

        <div class="layout-template-toolbar border-b app-border p-5 sm:p-6 lg:sticky lg:top-0 lg:z-20">
            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] xl:items-end">
                <fieldset>
                    <legend class="mb-3 text-sm font-extrabold app-text">Cấu hình lưới</legend>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-bold app-muted">Hàng</span>
                            <input data-layout-rows type="number" min="1" max="30" step="1" class="cinema-input min-h-11" value="{{ $initial['rows'] }}">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-bold app-muted">Cột</span>
                            <input data-layout-columns type="number" min="1" max="40" step="1" class="cinema-input min-h-11" value="{{ $initial['columns'] }}">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-bold app-muted">Vị trí màn hình</span>
                            <select data-layout-screen class="cinema-input min-h-11">
                                <option value="top" @selected($initial['screen_position'] === 'top')>Phía trên</option>
                                <option value="bottom" @selected($initial['screen_position'] === 'bottom')>Phía dưới</option>
                            </select>
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="mb-3 text-sm font-extrabold app-text">Công cụ bố trí</legend>
                    <div data-layout-tools class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5" role="toolbar" aria-label="Công cụ bố trí sơ đồ ghế">
                        @foreach($tools as $key => $tool)
                            <button type="button" data-layout-tool="{{ $key }}" aria-pressed="{{ $key === 'normal' ? 'true' : 'false' }}" class="layout-template-tool {{ $key === 'normal' ? 'is-active' : '' }} {{ $key === 'empty' ? 'is-danger' : '' }}">
                                <i class="ph {{ $tool['icon'] }} text-lg" aria-hidden="true"></i>
                                <span>{{ $tool['label'] }}</span>
                                <i data-tool-check class="ph-bold ph-check ml-auto {{ $key === 'normal' ? '' : 'invisible' }}" aria-hidden="true"></i>
                            </button>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <p data-layout-editor-message class="mt-3 hidden rounded-xl border border-warning/35 bg-warning/10 px-4 py-3 text-sm text-warning" role="status" aria-live="polite"></p>
        </div>

        @error('layout')
            <div class="mx-5 mt-5 rounded-2xl border border-error/35 bg-error/10 px-4 py-3 text-sm font-semibold text-error sm:mx-6" role="alert">
                <i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>{{ $message }}
            </div>
        @enderror

        <div class="p-4 sm:p-6">
            <x-admin.layout-template-legend />

            <div class="mt-5 max-w-full overflow-x-auto rounded-2xl border app-border app-bg p-4 sm:p-6" data-layout-scroll-region tabindex="0" aria-label="Sơ đồ ghế có thể cuộn ngang">
                <div data-layout-stage class="mx-auto w-max min-w-fit">
                    <div data-layout-screen-visual class="layout-template-screen" aria-label="Màn hình ở phía {{ $initial['screen_position'] === 'top' ? 'trên' : 'dưới' }}">
                        <span>MÀN HÌNH</span>
                    </div>
                    <div data-layout-grid class="layout-template-grid" role="grid" aria-describedby="seat-layout-editor-help"></div>
                </div>
            </div>
        </div>
    </section>

    <section class="app-card rounded-3xl border app-border p-5 sm:p-6" aria-labelledby="layout-statistics-title">
        <div class="mb-5">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Thống kê</p>
            <h2 id="layout-statistics-title" class="mt-2 text-xl font-extrabold app-text">Sức chứa logic của mẫu</h2>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                'capacity' => ['Sức chứa', 'ph-users-three'],
                'normal' => ['Ghế thường', 'ph-armchair'],
                'vip' => ['VIP', 'ph-star'],
                'couple' => ['Ghế đôi', 'ph-heart'],
                'aisle' => ['Lối đi', 'ph-arrows-down-up'],
                'dimensions' => ['Hàng × cột', 'ph-grid-four'],
            ] as $key => [$label, $icon])
                <div class="rounded-2xl border app-border app-bg p-4">
                    <div class="flex items-center gap-2 text-xs font-bold app-muted"><i class="ph {{ $icon }} text-brand-start" aria-hidden="true"></i>{{ $label }}</div>
                    <p data-layout-stat="{{ $key }}" class="mt-2 text-xl font-black app-text">0</p>
                    @if($key === 'capacity')<p class="mt-1 text-xs app-muted">ghế logic</p>@endif
                    @if($key === 'couple')<p data-layout-couple-positions class="mt-1 text-xs app-muted">0 vị trí</p>@endif
                </div>
            @endforeach
        </div>
    </section>

    <input type="hidden" name="layout" data-layout-json>
    <script type="application/json" data-layout-template-seed>@json($initial)</script>

    <div class="flex flex-col-reverse gap-3 rounded-2xl border app-border app-card p-4 sm:flex-row sm:items-center sm:justify-end">
        <a class="btn-secondary w-full sm:w-auto" href="{{ route('admin.layout-templates.index') }}"><i class="ph ph-x" aria-hidden="true"></i>Hủy</a>
        <button type="submit" class="btn-primary w-full sm:w-auto" data-loading-label="Đang lưu…"><i class="ph-fill ph-floppy-disk" aria-hidden="true"></i>{{ $submitLabel }}</button>
        <span data-submit-status class="sr-only" role="status" aria-live="polite"></span>
    </div>
</form>
