import QRCode from 'qrcode';
import html2canvas from 'html2canvas';

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
    const modalTrigger = event.target.closest('[data-modal-open]');
    const modalClose = event.target.closest('[data-modal-close]');

    if (modalTrigger || modalClose) {
        const modalId = modalTrigger?.dataset.modalOpen || modalClose?.dataset.modalClose;
        const modal = modalId ? document.getElementById(modalId) : null;

        if (modal) {
            const shouldOpen = Boolean(modalTrigger);
            modal.hidden = !shouldOpen;
            modal.classList.toggle('hidden', !shouldOpen);
            modal.classList.toggle('flex', shouldOpen);
            document.body.classList.toggle('overflow-hidden', shouldOpen);
        }

        return;
    }

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

const posterFallback = `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" width="500" height="750" viewBox="0 0 500 750">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#151A27"/><stop offset="1" stop-color="#080A12"/></linearGradient></defs>
  <rect width="500" height="750" fill="url(#g)"/>
  <circle cx="250" cy="310" r="92" fill="#FF3D57" opacity=".16"/>
  <path d="M198 260h104a20 20 0 0 1 20 20v88a20 20 0 0 1-20 20H198a20 20 0 0 1-20-20v-88a20 20 0 0 1 20-20zm28 35v58l58-29-58-29z" fill="#fff" opacity=".82"/>
  <text x="250" y="480" text-anchor="middle" fill="#fff" font-family="Arial,sans-serif" font-size="36" font-weight="700">MovieMate</text>
</svg>`)}`;

document.addEventListener('error', (event) => {
    const image = event.target;

    if (!(image instanceof HTMLImageElement) || image.dataset.fallbackApplied === 'true') {
        return;
    }

    image.dataset.fallbackApplied = 'true';
    image.src = posterFallback.trim();
}, true);

async function renderTicketQrCodes() {
    const canvases = document.querySelectorAll('canvas[data-qr-value]');

    await Promise.all(Array.from(canvases, async (canvas) => {
        const value = canvas.dataset.qrValue;
        if (!value) return;

        const width = Number.parseInt(canvas.dataset.qrSize || '200', 10);
        await QRCode.toCanvas(canvas, value, {
            width,
            margin: 1,
            color: { dark: '#111827', light: '#ffffff' },
            errorCorrectionLevel: 'M',
        });
    }));
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-ticket-download]');
    if (!button) return;

    const target = document.getElementById(button.dataset.ticketDownload);
    if (!target) return;

    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="ph-bold ph-spinner-gap animate-spin"></i> Đang tạo ảnh';

    try {
        const canvas = await html2canvas(target, {
            backgroundColor: '#ffffff',
            scale: Math.min(window.devicePixelRatio || 2, 3),
            useCORS: true,
        });
        const link = document.createElement('a');
        link.download = button.dataset.ticketFilename || 'moviemate-ticket.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (error) {
        window.alert('Không thể tạo ảnh vé. Vui lòng thử lại.');
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
});

renderTicketQrCodes().catch(() => {
    document.querySelectorAll('[data-qr-fallback]').forEach((element) => element.classList.remove('hidden'));
});
