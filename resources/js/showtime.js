// MERGE REPAIR - the incoming branch committed this module with placeholder bodies written
// literally as "{ ... }", which is not valid JavaScript and fails the production build.
// origin/main was therefore already unbuildable before this merge.
//
// The placeholders are replaced with safe no-ops so the bundle compiles and existing
// server-rendered links keep working via normal navigation. The intended behaviour
// (Ajax showtime filtering) is NOT implemented here, because inventing it would go beyond
// reconciling the two histories. The owning branch should finish these.
const SHOWTIME_QUERY_KEYS = ['cinema_id', 'date', 'city', 'brand', 'nearby', 'lat', 'lng'];

function shouldPinShowtimeSection() {
    const params = new URLSearchParams(window.location.search);

    return window.location.hash === '#home-showtime-calendar'
        || SHOWTIME_QUERY_KEYS.some((key) => params.has(key));
}

function scrollToShowtimeSection() {
    document.getElementById('home-showtime-calendar')?.scrollIntoView({ block: 'start' });
}

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
function setNearbyButtonLoading(button, isLoading) {
    if (!button) return;
    button.disabled = isLoading;
    button.setAttribute('aria-busy', String(isLoading));
}

function redirectToNearby(latitude, longitude) {
    const target = new URL(window.location.href);
    target.searchParams.set('nearby', '1');
    target.searchParams.set('lat', String(latitude));
    target.searchParams.set('lng', String(longitude));
    target.hash = 'home-showtime-calendar';
    window.location.assign(target.toString());
}

function handleNearbyError(button) {
    setNearbyButtonLoading(button, false);
}

function requestNearbyLocation(button) {
    if (!navigator.geolocation) {
        handleNearbyError(button);

        return;
    }

    setNearbyButtonLoading(button, true);
    navigator.geolocation.getCurrentPosition(
        (position) => redirectToNearby(position.coords.latitude, position.coords.longitude),
        () => handleNearbyError(button),
    );
}

// Full-page navigation is the safe fallback until Ajax filtering is implemented.
function updateShowtimeSection(targetUrl) {
    window.location.assign(targetUrl.toString());
}
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
