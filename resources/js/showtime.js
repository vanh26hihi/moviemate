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