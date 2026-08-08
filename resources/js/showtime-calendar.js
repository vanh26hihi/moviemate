const ACTIVE_BUTTON_CLASSES = [
    'border-transparent',
    'bg-gradient-to-br',
    'from-brand-start',
    'to-brand-end',
    'text-white',
];
const INACTIVE_BUTTON_CLASSES = ['app-border', 'app-secondary', 'app-text'];

function setButtonState(button, active) {
    button.setAttribute('aria-pressed', String(active));
    ACTIVE_BUTTON_CLASSES.forEach((className) => button.classList.toggle(className, active));
    INACTIVE_BUTTON_CLASSES.forEach((className) => button.classList.toggle(className, !active));

    const availability = button.querySelector('[data-showtime-availability]');
    availability?.classList.toggle('bg-white', active);
    availability?.classList.toggle('bg-brand-start', !active);
}

function keepButtonVisible(calendar, button) {
    const strip = calendar.querySelector('[data-showtime-date-strip]');
    if (!(strip instanceof HTMLElement)) return;

    const buttonStart = button.offsetLeft;
    const buttonEnd = buttonStart + button.offsetWidth;
    const visibleStart = strip.scrollLeft;
    const visibleEnd = visibleStart + strip.clientWidth;

    if (buttonStart < visibleStart) {
        strip.scrollLeft = buttonStart;
    } else if (buttonEnd > visibleEnd) {
        strip.scrollLeft += buttonEnd - visibleEnd;
    }
}

function selectDate(calendar, date, updateHistory = true) {
    const button = calendar.querySelector(`[data-showtime-date="${CSS.escape(date)}"]`);
    const datePanel = calendar.querySelector(`[data-showtime-panel="${CSS.escape(date)}"]`);
    const emptyPanel = calendar.querySelector('[data-showtime-empty-panel]');
    const panel = datePanel || emptyPanel;
    if (!(button instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) return false;

    calendar.querySelectorAll('[data-showtime-date]').forEach((candidate) => {
        setButtonState(candidate, candidate === button);
    });
    calendar.querySelectorAll('[data-showtime-panel], [data-showtime-empty-panel]').forEach((candidate) => {
        candidate.hidden = candidate !== panel;
    });
    if (panel === emptyPanel) panel.setAttribute('aria-labelledby', button.id);
    calendar.dataset.selectedDate = date;
    keepButtonVisible(calendar, button);

    if (updateHistory) {
        const url = new URL(window.location.href);
        url.searchParams.set('date', date);
        window.history.pushState({ ...window.history.state, showtimeDate: date }, '', url);
    }

    return true;
}

export function initializeShowtimeCalendars() {
    const calendar = document.querySelector('#home-showtime-calendar[data-showtime-calendar]');
    if (!(calendar instanceof HTMLElement) || calendar.dataset.showtimeCalendarInitialized === 'true') return;

    calendar.dataset.showtimeCalendarInitialized = 'true';
    calendar.dataset.initialShowtimeDate = calendar.dataset.selectedDate;

    calendar.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest('[data-showtime-date]');
        if (!(button instanceof HTMLButtonElement) || !calendar.contains(button)) return;

        const date = button.dataset.showtimeDate;
        if (!date || date === calendar.dataset.selectedDate) return;

        selectDate(calendar, date);
    });

    if (document.documentElement.dataset.showtimePopstateInitialized === 'true') return;
    document.documentElement.dataset.showtimePopstateInitialized = 'true';
    window.addEventListener('popstate', () => {
        const requestedDate = new URL(window.location.href).searchParams.get('date');
        const date = requestedDate || calendar.dataset.initialShowtimeDate;
        if (date) selectDate(calendar, date, false);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeShowtimeCalendars, { once: true });
} else {
    initializeShowtimeCalendars();
}

window.addEventListener('pageshow', initializeShowtimeCalendars);
