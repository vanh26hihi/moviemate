@php
    $currentFormatIds = isset($movie)
        ? $movie->supportedPresentationFormats->pluck('id')->map(fn ($id) => (int) $id)->all()
        : [];
    $selectedFormatIds = collect(old('presentation_format_ids', $currentFormatIds))->map(fn ($id) => (int) $id);
@endphp

<fieldset class="rounded-2xl border app-border app-card-soft p-4">
    <legend class="px-2 text-sm font-extrabold app-text">Định dạng hỗ trợ</legend>
    <p class="mb-3 text-xs app-muted">Chọn một hoặc nhiều định dạng đang sử dụng. Bản nháp có thể lưu tạm khi chưa chọn; phim phải có ít nhất một định dạng trước khi được đưa vào lịch.</p>
    <div class="grid gap-3 sm:grid-cols-2">
        @forelse($presentationFormats as $format)
            <label class="flex items-center gap-3 rounded-xl border app-border px-4 py-3">
                <input type="checkbox" name="presentation_format_ids[]" value="{{ $format->id }}" @checked($selectedFormatIds->contains((int) $format->id))>
                <span><strong class="app-text">{{ $format->name }}</strong><span class="ml-1 text-xs app-muted">{{ $format->code }}</span></span>
            </label>
        @empty
            <p class="text-sm text-error">Chưa có định dạng trình chiếu đang sử dụng.</p>
        @endforelse
        @foreach(($archivedPresentationFormats ?? collect()) as $format)
            <label class="flex items-center gap-3 rounded-xl border border-warning/30 bg-warning/5 px-4 py-3">
                <input type="checkbox" name="presentation_format_ids[]" value="{{ $format->id }}" @checked($selectedFormatIds->contains((int) $format->id))>
                <span><strong class="app-text">{{ $format->name }}</strong><span class="ml-1 text-xs text-warning">Đã lưu trữ · bỏ chọn để gỡ liên kết</span></span>
            </label>
        @endforeach
    </div>
    @error('presentation_format_ids')<p class="mt-2 text-sm font-semibold text-error">{{ $message }}</p>@enderror
    @error('presentation_format_ids.*')<p class="mt-2 text-sm font-semibold text-error">{{ $message }}</p>@enderror
</fieldset>
