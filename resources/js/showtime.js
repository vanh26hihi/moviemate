const SHOWTIME_QUERY_KEYS = ['cinema_id', 'date', 'city', 'brand', 'nearby', 'lat', 'lng'];

function shouldPinShowtimeSection() { ... }

function scrollToShowtimeSection() { ... }

if (shouldPinShowtimeSection()) {
    document.documentElement.style.scrollBehavior = 'auto';
}

document.addEventListener('DOMContentLoaded', () => {
    if (shouldPinShowtimeSection()) {
        requestAnimationFrame(scrollToShowtimeSection);
    }
});

window.addEventListener('load', () => {
    if (shouldPinShowtimeSection()) {
        scrollToShowtimeSection();
    }
});
function setNearbyButtonLoading(button, isLoading) { ... }

function redirectToNearby(latitude, longitude) { ... }

function handleNearbyError(error) { ... }

function requestNearbyLocation(button) { ... }
function setShowtimeLoading(section, isLoading) { ... }

async function updateShowtimeSection(targetUrl) { ... }
window.addEventListener('popstate', () => {
    if (shouldPinShowtimeSection()) {
        window.location.reload();
    }
});
document.addEventListener('click', (event) => {
    const nearbyButton = event.target.closest('#nearbyCinemaBtn');

    if (nearbyButton) {
        event.preventDefault();
        requestNearbyLocation(nearbyButton);
        return;
    }

    const link = event.target.closest('a[data-showtime-filter]');

    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();

    const targetUrl = new URL(link.href, window.location.origin);

    if (targetUrl.hash !== '#home-showtime-calendar') {
        targetUrl.hash = 'home-showtime-calendar';
    }

    updateShowtimeSection(targetUrl);
});