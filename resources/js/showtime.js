let activeRequest = null;
let requestSequence = 0;

function nearbyStatus(message) {
    const status = document.getElementById('nearbyCinemaStatus');
    if (status) status.textContent = message;
}

export function haversineDistance(originLatitude, originLongitude, targetLatitude, targetLongitude) {
    const radians = (degrees) => degrees * Math.PI / 180;
    const latitudeDelta = radians(targetLatitude - originLatitude);
    const longitudeDelta = radians(targetLongitude - originLongitude);
    const a = Math.sin(latitudeDelta / 2) ** 2
        + Math.cos(radians(originLatitude)) * Math.cos(radians(targetLatitude))
        * Math.sin(longitudeDelta / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function coordinateEntries(button) {
    const select = button.dataset.nearbyTarget ? document.getElementById(button.dataset.nearbyTarget) : null;
    if (select instanceof HTMLSelectElement) {
        return [...select.options].filter((option) => option.dataset.latitude && option.dataset.longitude)
            .map((option) => ({ node: option, latitude: Number(option.dataset.latitude), longitude: Number(option.dataset.longitude) }));
    }
    const list = button.dataset.nearbyList ? document.getElementById(button.dataset.nearbyList) : null;
    return list ? [...list.querySelectorAll('[data-cinema-card]')]
        .filter((card) => card.dataset.latitude && card.dataset.longitude)
        .map((card) => ({ node: card, latitude: Number(card.dataset.latitude), longitude: Number(card.dataset.longitude) })) : [];
}

function requestNearbyCinema(button) {
    if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(window.location.hostname)) {
        nearbyStatus('Tính năng vị trí cần HTTPS hoặc localhost. Hãy chọn chi nhánh thủ công.');
        return;
    }
    if (!navigator.geolocation) {
        nearbyStatus('Không xác định được vị trí hiện tại.');
        return;
    }
    const entries = coordinateEntries(button);
    if (entries.length === 0) {
        nearbyStatus('Chưa có dữ liệu vị trí cho các chi nhánh.');
        return;
    }
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    navigator.geolocation.getCurrentPosition((position) => {
        const ranked = entries.map((entry) => ({ ...entry, distance: haversineDistance(
            position.coords.latitude, position.coords.longitude, entry.latitude, entry.longitude,
        ) })).sort((left, right) => left.distance - right.distance);
        const nearest = ranked[0];
        if (nearest.node instanceof HTMLOptionElement) {
            nearest.node.parentElement.value = nearest.node.value;
            nearest.node.parentElement.form?.requestSubmit();
        } else {
            const parent = nearest.node.parentElement;
            ranked.forEach(({ node, distance }, index) => {
                const label = node.querySelector('[data-cinema-distance]');
                if (label) {
                    label.textContent = `${index === 0 ? 'Gần bạn nhất · ' : ''}${distance.toLocaleString('vi-VN', { maximumFractionDigits: 1 })} km`;
                    label.classList.remove('hidden');
                }
                parent.appendChild(node);
            });
            nearest.node.scrollIntoView({ behavior: 'smooth', block: 'center' });
            nearbyStatus('Đã xếp chi nhánh theo khoảng cách gần nhất.');
        }
    }, (error) => {
        nearbyStatus(error.code === error.PERMISSION_DENIED
            ? 'Bạn chưa cho phép truy cập vị trí. Hãy chọn chi nhánh thủ công.'
            : 'Không xác định được vị trí hiện tại.');
    }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
    button.disabled = false;
    button.removeAttribute('aria-busy');
}

function fullPageUrl(form, submitter = null) {
    const url = new URL(form.action, window.location.origin);
    const data = new FormData(form);
    if (submitter?.name) data.set(submitter.name, submitter.value);
    for (const [key, value] of data.entries()) if (String(value) !== '') url.searchParams.set(key, String(value));
    return url;
}

function partialUrl(form, pageUrl) {
    const url = new URL(form.dataset.filterEndpoint, window.location.origin);
    url.searchParams.set('context', form.dataset.filterContext);
    if (form.dataset.cinemaCode) url.searchParams.set('cinema', form.dataset.cinemaCode);
    if (form.dataset.movieSlug) url.searchParams.set('movie', form.dataset.movieSlug);
    for (const name of ['cinema', 'date']) {
        const value = pageUrl.searchParams.get(name);
        if (value) url.searchParams.set(name, value);
    }
    return url;
}

function syncForm(form, pageUrl, effectiveDate = null) {
    for (const name of ['cinema', 'date']) {
        const value = name === 'date'
            ? effectiveDate || pageUrl.searchParams.get(name) || form.dataset.defaultDate || ''
            : pageUrl.searchParams.get(name) || '';
        form.querySelectorAll(`[name="${name}"]`).forEach((control) => {
            if (control instanceof HTMLSelectElement) control.value = value;
            if (control instanceof HTMLButtonElement) {
                const button = control;
                const selected = button.value === value;
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
                if (selected) button.setAttribute('aria-current', 'date');
                else button.removeAttribute('aria-current');
            }
        });
    }

    if (effectiveDate) form.dataset.defaultDate = effectiveDate;
}

function syncResultSummary(target) {
    const metadata = target.querySelector('[data-showtime-result-meta]');
    const summary = document.querySelector('[data-showtime-count]');
    if (!(metadata instanceof HTMLElement) || !(summary instanceof HTMLElement)) return;

    const count = Number.parseInt(metadata.dataset.showtimeResultCount || '', 10);
    if (Number.isFinite(count)) summary.textContent = `${count} suất chiếu đang khả dụng`;
}

async function updateShowtimes(form, pageUrl, pushHistory) {
    const target = document.querySelector('[data-showtime-results]');
    const status = document.querySelector('[data-showtime-filter-status]');
    if (!(target instanceof HTMLElement) || !form.dataset.filterEndpoint) return window.location.assign(pageUrl.toString());
    activeRequest?.abort();
    activeRequest = new AbortController();
    const sequence = ++requestSequence;
    form.setAttribute('aria-busy', 'true');
    if (status) status.textContent = 'Đang tải lịch chiếu…';
    try {
        const response = await fetch(partialUrl(form, pageUrl), { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: activeRequest.signal });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const html = await response.text();
        if (sequence !== requestSequence) return;
        target.innerHTML = html;
        const metadata = target.querySelector('[data-showtime-result-meta]');
        const effectiveDate = metadata instanceof HTMLElement ? metadata.dataset.showtimeResultDate : null;
        syncForm(form, pageUrl, effectiveDate);
        syncResultSummary(target);
        if (pushHistory) window.history.pushState({ showtimeFilters: true }, '', pageUrl);
        if (status) status.textContent = 'Đã cập nhật lịch chiếu.';
    } catch (error) {
        if (error.name !== 'AbortError') window.location.assign(pageUrl.toString());
    } finally {
        if (sequence === requestSequence) form.removeAttribute('aria-busy');
    }
}

function initializeDiscovery() {
    if (document.documentElement.dataset.cinemaDiscoveryInitialized === 'true') return;
    document.documentElement.dataset.cinemaDiscoveryInitialized = 'true';
    document.addEventListener('click', (event) => {
        const nearby = event.target.closest('#nearbyCinemaBtn');
        if (nearby instanceof HTMLButtonElement) { event.preventDefault(); requestNearbyCinema(nearby); }
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
