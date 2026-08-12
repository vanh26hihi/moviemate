const DEBOUNCE_MS = 300;

function initializeShowtimeSchedulePreview(preview) {
    if (preview.dataset.initialized === 'true') return;
    preview.dataset.initialized = 'true';

    const form = preview.closest('form');
    if (!form) return;

    const fields = {
        movie_id: form.querySelector('[name="movie_id"]'),
        room_id: form.querySelector('[name="room_id"]'),
        show_date: form.querySelector('[name="show_date"]'),
        show_time: form.querySelector('[name="show_time"]'),
    };
    if (Object.values(fields).some((field) => !field)) return;

    const state = preview.querySelector('[data-schedule-preview-state]');
    const timezone = preview.querySelector('[data-schedule-timezone]');
    const start = preview.querySelector('[data-schedule-start]');
    const end = preview.querySelector('[data-schedule-end]');
    const cleaning = preview.querySelector('[data-schedule-cleaning]');
    const ready = preview.querySelector('[data-schedule-ready]');
    const conflict = preview.querySelector('[data-schedule-conflict]');
    const conflictMovie = preview.querySelector('[data-conflict-movie]');
    const conflictWindow = preview.querySelector('[data-conflict-window]');
    const conflictReady = preview.querySelector('[data-conflict-ready]');
    const save = form.querySelector('[data-showtime-save]');
    let timer = null;
    let sequence = 0;
    let controller = null;

    function setState(message, kind = 'neutral') {
        state.textContent = message;
        state.classList.toggle('text-success', kind === 'valid');
        state.classList.toggle('text-error', kind === 'invalid');
        state.classList.toggle('app-muted', kind === 'neutral' || kind === 'loading');
    }

    function clearWindow() {
        start.textContent = '--';
        end.textContent = '--';
        cleaning.textContent = '--';
        ready.textContent = '--';
        conflict.hidden = true;
    }

    function isComplete() {
        return Object.values(fields).every((field) => field.value !== '');
    }

    function render(data) {
        timezone.textContent = data.timezone || fields.room_id.selectedOptions[0]?.dataset.timezone || preview.dataset.timezone;
        if (data.window) {
            start.textContent = data.window.start_display;
            end.textContent = data.window.end_display;
            cleaning.textContent = data.window.cleaning_display;
            ready.textContent = data.window.room_ready_display;
        }

        if (data.valid) {
            setState('Khung giờ hợp lệ.', 'valid');
            if (save) save.disabled = false;
            return;
        }

        setState(data.message || 'Khung giờ không hợp lệ.', 'invalid');
        if (save) save.disabled = true;
        if (data.conflict) {
            conflict.hidden = false;
            conflictMovie.textContent = `${data.conflict.movie || 'Suất chiếu khác'} · ${data.conflict.room_code || data.conflict.room || ''}`;
            conflictWindow.textContent = `${data.conflict.start_display} – ${data.conflict.end_display}`;
            conflictReady.textContent = `Phòng sẵn sàng lúc ${data.conflict.room_ready_display}`;
        }
    }

    async function requestPreview(requestSequence) {
        controller = new AbortController();
        setState('Đang kiểm tra khung giờ…', 'loading');
        if (save) save.disabled = true;

        const body = new FormData();
        Object.entries(fields).forEach(([name, field]) => body.append(name, field.value));
        if (preview.dataset.showtimeId) body.append('showtime_id', preview.dataset.showtimeId);

        try {
            const response = await fetch(preview.dataset.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]')?.value || '',
                },
                body,
                signal: controller.signal,
            });
            const data = await response.json();
            if (requestSequence !== sequence) return;
            if (!response.ok) {
                setState(Object.values(data.errors || {}).flat()[0] || 'Không thể kiểm tra dữ liệu khung giờ.', 'invalid');
                if (save) save.disabled = true;
                return;
            }
            render(data);
        } catch (error) {
            if (error.name === 'AbortError' || requestSequence !== sequence) return;
            setState('Không thể kiểm tra trước khung giờ lúc này. Hệ thống sẽ kiểm tra lại khi lưu.', 'neutral');
            if (save) save.disabled = false;
        }
    }

    function invalidateAndSchedule() {
        sequence++;
        controller?.abort();
        clearTimeout(timer);
        clearWindow();
        timezone.textContent = fields.room_id.selectedOptions[0]?.dataset.timezone || preview.dataset.timezone;

        if (!isComplete()) {
            setState('Chọn đủ phim, phòng, ngày và giờ bắt đầu để kiểm tra khung giờ.');
            if (save) save.disabled = false;
            return;
        }

        setState('Đang chờ kiểm tra khung giờ…', 'loading');
        if (save) save.disabled = true;
        const requestSequence = sequence;
        timer = setTimeout(() => requestPreview(requestSequence), DEBOUNCE_MS);
    }

    Object.values(fields).forEach((field) => field.addEventListener('change', invalidateAndSchedule));
    invalidateAndSchedule();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-showtime-schedule-preview]').forEach(initializeShowtimeSchedulePreview);
});
