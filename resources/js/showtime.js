let activeRequest = null;
let requestSequence = 0;

function nearbyStatus(message) {
    const status = document.getElementById('nearbyCinemaStatus');
    if (status) status.textContent = message;
}

function requestNearbyCinema(button) {
    if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(window.location.hostname)) {
        nearbyStatus('Tính năng vị trí cần HTTPS hoặc localhost. Bạn vẫn có thể chọn rạp thủ công.');
        return;
    }
    if (!navigator.geolocation) {
        nearbyStatus('Không thể truy cập vị trí của bạn. Vui lòng chọn rạp thủ công.');
        return;
    }
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    nearbyStatus('Đang xác định vị trí gần đúng của bạn…');
    navigator.geolocation.getCurrentPosition((position) => {
        const url = new URL(button.dataset.nearbyUrl || window.location.href, window.location.origin);
        url.searchParams.set('nearby', '1');
        url.searchParams.set('sort', 'nearby');
        url.searchParams.set('lat', String(position.coords.latitude));
        url.searchParams.set('lng', String(position.coords.longitude));
        window.location.assign(url.toString());
    }, () => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        nearbyStatus('Không thể truy cập vị trí của bạn. Vui lòng chọn rạp thủ công.');
    }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
}

function fullPageUrl(form, submitter = null) {
    const url = new URL(form.action, window.location.origin);
    const data = new FormData(form);
    if (submitter?.name) data.set(submitter.name, submitter.value);
    for (const [key, value] of data.entries()) {
        if (String(value) !== '') url.searchParams.set(key, String(value));
    }
    return url;
}

function partialUrl(form, pageUrl) {
    const url = new URL(form.dataset.filterEndpoint, window.location.origin);
    url.searchParams.set('context', form.dataset.filterContext);
    if (form.dataset.cinemaCode) url.searchParams.set('cinema', form.dataset.cinemaCode);
    if (form.dataset.movieSlug) url.searchParams.set('movie', form.dataset.movieSlug);
    const cinema = pageUrl.searchParams.get('cinema');
    const date = pageUrl.searchParams.get('date');
    if (cinema) url.searchParams.set('cinema', cinema);
    if (date) url.searchParams.set('date', date);
    return url;
}

function syncForm(form, pageUrl) {
    for (const name of ['cinema', 'date']) {
        const value = pageUrl.searchParams.get(name) || '';
        form.querySelectorAll(`[name="${name}"]`).forEach((control) => {
            if (control instanceof HTMLSelectElement) control.value = value;
        });
    }
    const selectedDate = pageUrl.searchParams.get('date') || '';
    form.querySelectorAll('button[name="date"]').forEach((button) => {
        const selected = button.value === selectedDate;
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        button.classList.toggle('border-brand-start', selected);
        button.classList.toggle('bg-brand-start', selected);
        button.classList.toggle('text-white', selected);
        button.classList.toggle('app-border', !selected);
        button.classList.toggle('app-secondary', !selected);
        button.classList.toggle('app-text', !selected);
    });
}

async function updateShowtimes(form, pageUrl, pushHistory) {
    const target = document.querySelector('[data-showtime-results]');
    const status = document.querySelector('[data-showtime-filter-status]');
    if (!(target instanceof HTMLElement) || !form.dataset.filterEndpoint) {
        window.location.assign(pageUrl.toString());
        return;
    }
    activeRequest?.abort();
    activeRequest = new AbortController();
    const sequence = ++requestSequence;
    form.setAttribute('aria-busy', 'true');
    if (status) status.textContent = 'Đang tải lịch chiếu…';
    try {
        const response = await fetch(partialUrl(form, pageUrl), {
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            signal: activeRequest.signal,
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const html = await response.text();
        if (sequence !== requestSequence) return;
        target.innerHTML = html;
        syncForm(form, pageUrl);
        if (pushHistory) window.history.pushState({ showtimeFilters: true }, '', pageUrl);
        if (status) status.textContent = 'Đã cập nhật lịch chiếu.';
    } catch (error) {
        if (error.name === 'AbortError') return;
        if (status) status.textContent = 'Không thể tải lịch chiếu. Đang chuyển sang trang đầy đủ.';
        window.location.assign(pageUrl.toString());
    } finally {
        if (sequence === requestSequence) form.removeAttribute('aria-busy');
    }
}

function initializeDiscovery() {
    if (document.documentElement.dataset.cinemaDiscoveryInitialized === 'true') return;
    document.documentElement.dataset.cinemaDiscoveryInitialized = 'true';

    document.addEventListener('click', (event) => {
        const nearby = event.target.closest('#nearbyCinemaBtn');
        if (nearby instanceof HTMLButtonElement) {
            event.preventDefault();
            requestNearbyCinema(nearby);
        }
    });
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-showtime-filter-form]');
        if (!(form instanceof HTMLFormElement)) return;
        event.preventDefault();
        updateShowtimes(form, fullPageUrl(form, event.submitter), true);
    });
    window.addEventListener('popstate', () => {
        const form = document.querySelector('[data-showtime-filter-form]');
        if (form instanceof HTMLFormElement) updateShowtimes(form, new URL(window.location.href), false);
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeDiscovery, { once: true });
else initializeDiscovery();
