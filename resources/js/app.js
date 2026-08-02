const THEME_KEY = 'theme';
const LEGACY_THEME_KEY = 'moviemate_theme';

function readTheme() {
    try {
        return localStorage.getItem(THEME_KEY) || localStorage.getItem(LEGACY_THEME_KEY) || 'dark';
    } catch (error) {
        return 'dark';
    }
}

function applyTheme(theme) {
    const isLight = theme === 'light';

    document.documentElement.classList.toggle('light', isLight);

    try {
        localStorage.setItem(THEME_KEY, isLight ? 'light' : 'dark');
        localStorage.setItem(LEGACY_THEME_KEY, isLight ? 'light' : 'dark');
    } catch (error) {
        // The interface remains usable when storage is unavailable.
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(isLight));
        button.setAttribute('title', isLight ? 'Đổi sang giao diện tối' : 'Đổi sang giao diện sáng');
    });

    document.querySelectorAll('.theme-icon').forEach((element) => {
        element.innerHTML = isLight ? '<i class="ph-fill ph-sun"></i>' : '<i class="ph-fill ph-moon"></i>';
    });

    document.querySelectorAll('.theme-text').forEach((element) => {
        element.textContent = isLight ? 'Sáng' : 'Tối';
    });
}

applyTheme(readTheme());

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-theme-toggle]')) {
        return;
    }

    applyTheme(document.documentElement.classList.contains('light') ? 'dark' : 'light');
});
