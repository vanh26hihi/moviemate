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
    const themeToggle = event.target.closest('[data-theme-toggle]');

    if (themeToggle) {
        applyTheme(document.documentElement.classList.contains('light') ? 'dark' : 'light');
        return;
    }

    const passwordToggle = event.target.closest('[data-password-toggle]');

    if (!passwordToggle) {
        return;
    }

    const input = document.getElementById(passwordToggle.dataset.passwordToggle);

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const showPassword = input.type === 'password';
    input.type = showPassword ? 'text' : 'password';
    passwordToggle.setAttribute('aria-label', showPassword ? 'Ẩn mật khẩu' : 'Hiển thị mật khẩu');

    const icon = passwordToggle.querySelector('i');
    if (icon) {
        icon.className = showPassword ? 'ph ph-eye-slash text-lg' : 'ph ph-eye text-lg';
    }
});
