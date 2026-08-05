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

const vndFormatter = new Intl.NumberFormat('vi-VN');

function formatVnd(amount) {
    return `${vndFormatter.format(Math.max(0, Number(amount) || 0))} VND`;
}

function initializeSeatPickers() {
    document.querySelectorAll('[data-seat-picker]').forEach((form) => {
        if (form.dataset.seatPickerInitialized === 'true') return;
        form.dataset.seatPickerInitialized = 'true';

        const buttons = Array.from(form.querySelectorAll('.seat-button:not(:disabled)'));
        const selected = new Map();
        const input = form.querySelector('#selectedSeatsInput');
        const display = document.getElementById('selectedSeatsDisplay');
        const totalDisplay = document.getElementById('totalAmountDisplay');
        const continueButton = document.getElementById('continueBookingButton');
        const hint = document.getElementById('seatSelectionHint');

        if (!(input instanceof HTMLInputElement) || !display || !totalDisplay || !continueButton) return;
        hint?.setAttribute('aria-live', 'polite');

        function targetsFor(button) {
            if (button.dataset.seatType !== 'couple') return [button];

            return buttons.filter((candidate) => (
                candidate.dataset.pairCode
                && candidate.dataset.pairCode === button.dataset.pairCode
            ));
        }

        function setSelected(button, value) {
            const seatId = button.dataset.seatId;
            if (!seatId) return;

            if (value) {
                selected.set(seatId, {
                    id: seatId,
                    code: button.dataset.seatCode || '',
                    price: Number(button.dataset.price) || 0,
                });
            } else {
                selected.delete(seatId);
            }

            button.setAttribute('aria-pressed', String(value));
        }

        function refresh() {
            const values = Array.from(selected.values());
            input.value = values.map((item) => item.id).join(',');
            display.textContent = values.length ? values.map((item) => item.code).join(', ') : 'Chưa chọn';
            totalDisplay.textContent = formatVnd(values.reduce((total, item) => total + item.price, 0));
            continueButton.disabled = values.length === 0;
        }

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const targets = targetsFor(button);
                const expectedCount = button.dataset.seatType === 'couple' ? 2 : 1;

                if (targets.length !== expectedCount) {
                    if (hint) hint.textContent = 'Cặp ghế đôi này hiện không khả dụng. Vui lòng chọn ghế khác.';
                    return;
                }

                const shouldSelect = !targets.every((target) => selected.has(target.dataset.seatId));
                targets.forEach((target) => setSelected(target, shouldSelect));
                refresh();

                if (hint && button.dataset.seatType === 'couple') {
                    hint.textContent = shouldSelect
                        ? `Đã chọn cả cặp ghế ${targets.map((target) => target.dataset.seatCode).join(' và ')}.`
                        : 'Đã bỏ chọn cả cặp ghế đôi.';
                }
            });
        });

        refresh();
    });
}

function initializeFoodPickers() {
    document.querySelectorAll('[data-food-picker]').forEach((form) => {
        if (form.dataset.foodPickerInitialized === 'true') return;
        form.dataset.foodPickerInitialized = 'true';

        const cards = Array.from(form.querySelectorAll('[data-food-card]'));
        const subtotalDisplay = document.querySelector('[data-food-subtotal]');
        const grandTotalDisplay = document.querySelector('[data-food-grand-total]');
        const seatSubtotal = Number(grandTotalDisplay?.dataset.seatSubtotal) || 0;

        function normalizedQuantity(input) {
            const minimum = Number(input.min) || 0;
            const maximum = Number(input.max) || 20;
            const value = Number.parseInt(input.value, 10);

            return Math.min(maximum, Math.max(minimum, Number.isFinite(value) ? value : minimum));
        }

        function refresh() {
            let subtotal = 0;

            cards.forEach((card) => {
                const input = card.querySelector('[data-food-quantity]');
                if (!(input instanceof HTMLInputElement)) return;

                const quantity = normalizedQuantity(input);
                const unitPrice = Number(card.dataset.unitPrice) || 0;
                const lineTotal = quantity * unitPrice;
                input.value = String(quantity);
                subtotal += lineTotal;

                const lineTotalDisplay = card.querySelector('[data-food-line-total]');
                if (lineTotalDisplay) lineTotalDisplay.textContent = formatVnd(lineTotal);

                const decrease = card.querySelector('[data-quantity-decrease]');
                const increase = card.querySelector('[data-quantity-increase]');
                if (decrease instanceof HTMLButtonElement) decrease.disabled = quantity <= Number(input.min || 0);
                if (increase instanceof HTMLButtonElement) increase.disabled = quantity >= Number(input.max || 20);
            });

            if (subtotalDisplay) subtotalDisplay.textContent = formatVnd(subtotal);
            if (grandTotalDisplay) grandTotalDisplay.textContent = formatVnd(seatSubtotal + subtotal);
        }

        form.addEventListener('click', (event) => {
            const button = event.target.closest('[data-quantity-decrease], [data-quantity-increase]');
            if (!button) return;

            const input = button.closest('[data-food-card]')?.querySelector('[data-food-quantity]');
            if (!(input instanceof HTMLInputElement)) return;

            const direction = button.hasAttribute('data-quantity-increase') ? 1 : -1;
            input.value = String(normalizedQuantity(input) + direction);
            refresh();
            input.focus();
        });

        form.addEventListener('input', (event) => {
            if (event.target.matches('[data-food-quantity]')) refresh();
        });

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-food-quantity]')) refresh();
        });

        refresh();
    });
}

function resetSubmitGuard(form) {
    delete form.dataset.submitting;
    form.querySelectorAll('[data-submitter-copy]').forEach((input) => input.remove());
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = button.dataset.submitInitiallyDisabled === 'true';
        button.removeAttribute('aria-disabled');

        if (button.dataset.submitIdleMarkup) {
            button.innerHTML = button.dataset.submitIdleMarkup;
            delete button.dataset.submitIdleMarkup;
        }
    });

    const status = form.querySelector('[data-submit-status]');
    if (status) status.textContent = '';
}

function initializeSubmitGuards() {
    document.querySelectorAll('form[data-submit-once]').forEach((form) => {
        if (form.dataset.submitGuardInitialized === 'true') return;
        form.dataset.submitGuardInitialized = 'true';

        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.dataset.submitInitiallyDisabled = String(button.disabled);
        });

        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';
            const submitter = event.submitter;

            if (submitter instanceof HTMLButtonElement && submitter.name) {
                const submittedValue = document.createElement('input');
                submittedValue.type = 'hidden';
                submittedValue.name = submitter.name;
                submittedValue.value = submitter.value;
                submittedValue.dataset.submitterCopy = 'true';
                form.appendChild(submittedValue);
            }

            const loadingLabel = submitter?.dataset.loadingLabel || 'Đang xử lý…';
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            });

            if (submitter instanceof HTMLButtonElement) {
                submitter.dataset.submitIdleMarkup = submitter.innerHTML;
                const spinner = document.createElement('i');
                spinner.className = 'ph-bold ph-spinner-gap animate-spin';
                spinner.setAttribute('aria-hidden', 'true');
                submitter.replaceChildren(spinner, document.createTextNode(loadingLabel));
            }

            const status = form.querySelector('[data-submit-status]');
            if (status) status.textContent = loadingLabel;
        });
    });
}

function initializeImagePreviews() {
    document.querySelectorAll('input[type="file"][data-image-preview]').forEach((input) => {
        if (input.dataset.imagePreviewInitialized === 'true') return;
        input.dataset.imagePreviewInitialized = 'true';

        let objectUrl = null;
        input.addEventListener('change', () => {
            const preview = document.getElementById(input.dataset.imagePreview || '');
            if (!(preview instanceof HTMLImageElement)) return;

            if (objectUrl) URL.revokeObjectURL(objectUrl);
            const file = input.files?.[0];
            if (!file) return;

            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.classList.remove('hidden');
            preview.parentElement?.querySelector('[data-image-fallback]')?.classList.add('hidden');
        });
    });
}

if (document.documentElement.dataset.submitGuardPageShowInitialized !== 'true') {
    document.documentElement.dataset.submitGuardPageShowInitialized = 'true';
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submit-once]').forEach(resetSubmitGuard);
    });
}

function initializeCountdowns() {
    document.querySelectorAll('[data-countdown]').forEach((element) => {
        if (element.dataset.countdownInitialized === 'true') return;
        element.dataset.countdownInitialized = 'true';

        const deadline = Date.parse(element.dataset.countdown || '');
        if (!Number.isFinite(deadline)) return;

        function refresh() {
            const remainingSeconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;

            element.textContent = remainingSeconds > 0
                ? `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
                : (element.dataset.expiredLabel || 'Đã hết thời gian');

            if (remainingSeconds === 0) window.clearInterval(intervalId);
        }

        const intervalId = window.setInterval(refresh, 1000);
        refresh();
    });
}

initializeSeatPickers();
initializeFoodPickers();
initializeSubmitGuards();
initializeImagePreviews();
initializeCountdowns();

document.addEventListener('click', (event) => {
    const mobileMenuButton = event.target.closest('#mobile-menu-btn');

    if (mobileMenuButton) {
        const mobileMenu = document.getElementById('mobile-menu');
        if (!mobileMenu) return;

        const willOpen = mobileMenu.classList.contains('hidden');
        mobileMenu.classList.toggle('hidden', !willOpen);
        mobileMenuButton.setAttribute('aria-expanded', String(willOpen));
        mobileMenuButton.setAttribute('aria-label', willOpen ? 'Đóng menu' : 'Mở menu');
        return;
    }

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

document.addEventListener('click', (event) => {
    document.querySelectorAll('.user-account-menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    const mobileMenu = document.getElementById('mobile-menu');
    const mobileMenuButton = document.getElementById('mobile-menu-btn');
    if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
        mobileMenu.classList.add('hidden');
        mobileMenuButton?.setAttribute('aria-expanded', 'false');
        mobileMenuButton?.setAttribute('aria-label', 'Mở menu');
        mobileMenuButton?.focus();
    }

    document.querySelectorAll('.user-account-menu[open]').forEach((menu) => menu.removeAttribute('open'));

    const openModal = document.querySelector('[data-modal]:not([hidden])');
    if (openModal) {
        openModal.hidden = true;
        openModal.classList.add('hidden');
        openModal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
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
    const printButton = event.target.closest('[data-print-ticket]');
    if (printButton) {
        window.print();

        return;
    }

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
