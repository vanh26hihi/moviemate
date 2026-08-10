const REFRESH_INTERVAL_MS = 30_000;

function authoritativeNow(element) {
    if (!Object.prototype.hasOwnProperty.call(element.dataset, 'serverClockOffset')) {
        const serverNow = Date.parse(element.dataset.serverNow || '');
        if (!Number.isFinite(serverNow)) return null;
        element.dataset.serverClockOffset = String(serverNow - Date.now());
    }

    const offset = Number(element.dataset.serverClockOffset);
    return Number.isFinite(offset) ? Date.now() + offset : null;
}

function timestamp(element, field) {
    const value = Date.parse(element.dataset[field] || '');
    return Number.isFinite(value) ? value : null;
}

function lifecycleState(element, now) {
    if (element.dataset.cancelled === 'true') return 'cancelled';

    const startsAt = timestamp(element, 'startAt');
    const endsAt = timestamp(element, 'endAt');
    if (startsAt === null || endsAt === null) return null;
    if (now < startsAt) return 'upcoming';
    if (now < endsAt) return 'playing';
    return 'completed';
}

const adminPresentation = {
    upcoming: { label: 'Sắp chiếu', classes: ['text-brand-start', 'bg-brand-start/10'] },
    playing: { label: 'Đang chiếu', classes: ['text-success', 'bg-success/10'] },
    completed: { label: 'Đã chiếu xong', classes: ['app-muted', 'app-secondary'] },
    cancelled: { label: 'Đã hủy', classes: ['text-error', 'bg-error/10'] },
};

const adminClasses = Object.values(adminPresentation).flatMap((item) => item.classes);

function refreshAdminLifecycle(element) {
    const now = authoritativeNow(element);
    if (now === null) return;
    const state = lifecycleState(element, now);
    const presentation = adminPresentation[state];
    if (!presentation) return;

    element.textContent = presentation.label;
    element.classList.remove(...adminClasses);
    element.classList.add(...presentation.classes);

    const row = element.closest('tr');
    const editAction = row?.querySelector('[data-showtime-edit-action]');
    const cancelAction = row?.querySelector('[data-showtime-cancel-action]');
    if (editAction instanceof HTMLElement) editAction.hidden = state !== 'upcoming';
    if (cancelAction instanceof HTMLElement) cancelAction.hidden = !['upcoming', 'playing'].includes(state);
}

function refreshCustomerShowtime(element) {
    if (element.dataset.bookingClosed === 'true') return;
    const now = authoritativeNow(element);
    const cutoffAt = timestamp(element, 'bookingCutoffAt');
    const endsAt = timestamp(element, 'endAt');
    if (now === null || cutoffAt === null || endsAt === null || (now < cutoffAt && now < endsAt)) return;

    element.dataset.bookingClosed = 'true';
    element.removeAttribute('href');
    element.setAttribute('aria-disabled', 'true');
    element.setAttribute('aria-label', 'Suất chiếu đã đóng đặt vé');
    element.classList.add('pointer-events-none', 'app-border', 'app-muted', 'opacity-60');
    element.classList.remove('border-brand-start/30', 'bg-brand-start/10', 'text-brand-start', 'hover:bg-brand-start', 'hover:text-white');
    element.querySelector('[data-showtime-booking-status]')?.classList.remove('hidden');
}

function refreshShowtimeLifecycle() {
    document.querySelectorAll('[data-showtime-lifecycle]').forEach(refreshAdminLifecycle);
    document.querySelectorAll('[data-customer-showtime]').forEach(refreshCustomerShowtime);
}

refreshShowtimeLifecycle();
window.setInterval(refreshShowtimeLifecycle, REFRESH_INTERVAL_MS);
window.addEventListener('pageshow', refreshShowtimeLifecycle);
