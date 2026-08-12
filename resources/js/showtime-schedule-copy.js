function initializeShowtimeScheduleCopy(workspace) {
    if (workspace.dataset.initialized === 'true') return;
    workspace.dataset.initialized = 'true';

    const cinema = workspace.querySelector('[data-copy-cinema]');
    const room = workspace.querySelector('[data-copy-room]');
    const scopes = [...workspace.querySelectorAll('[name="scope"]')];

    function sync() {
        const roomScope = scopes.find((input) => input.checked)?.value === 'room';
        const cinemaId = cinema?.value || workspace.dataset.cinemaId || '';

        if (room) {
            [...room.options].forEach((option) => {
                if (!option.value) return;
                const belongsToCinema = option.dataset.cinemaId === cinemaId;
                option.hidden = !belongsToCinema;
                option.disabled = !belongsToCinema;
                if (!belongsToCinema && option.selected) room.value = '';
            });
            room.disabled = !roomScope;
            room.required = roomScope;
        }
    }

    cinema?.addEventListener('change', sync);
    scopes.forEach((input) => input.addEventListener('change', sync));
    sync();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-showtime-schedule-copy]').forEach(initializeShowtimeScheduleCopy);
});
