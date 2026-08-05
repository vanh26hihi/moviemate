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