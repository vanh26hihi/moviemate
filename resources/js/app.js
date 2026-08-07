// Both sides added a distinct entry module: R1 ships the branch-aware calendar and the
// remote feature branch ships the cinema-finder/showtime filter helpers. They are
// independent, so the merge keeps both rather than choosing a side.
import './showtime-calendar';
import './showtime';
import './seat-gap-guard';

const ticketScannerWorkspaces = document.querySelectorAll('[data-ticket-scanner]');
if (ticketScannerWorkspaces.length > 0) {
    import('./ticket-scanner').catch(() => {
        ticketScannerWorkspaces.forEach((workspace) => {
            const error = workspace.querySelector('[data-scanner-error]');
            if (!error) return;
            error.textContent = 'Không thể tải trình quét camera. Vui lòng nhập mã vé thủ công.';
            error.hidden = false;
        });
    });
}

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
    return `${vndFormatter.format(Math.max(0, Number(amount) || 0))} VNĐ`;
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

        function setSelected(button, value) {
            const seatIds = (button.dataset.seatIds || '')
                .split(',')
                .map((seatId) => seatId.trim())
                .filter(Boolean);
            if (!seatIds.length) return;

            if (value) {
                selected.set(seatIds.join(','), {
                    ids: seatIds,
                    code: button.dataset.seatCode || '',
                    price: Number(button.dataset.price) || 0,
                });
            } else {
                selected.delete(seatIds.join(','));
            }

            button.setAttribute('aria-pressed', String(value));
        }

        function refresh() {
            const values = Array.from(selected.values());
            input.value = values.flatMap((item) => item.ids).join(',');
            display.textContent = values.length ? values.map((item) => item.code).join(', ') : 'Chưa chọn';
            totalDisplay.textContent = formatVnd(values.reduce((total, item) => total + item.price, 0));
            continueButton.disabled = values.length === 0;
            form.dispatchEvent(new CustomEvent('seat-selection:changed'));
        }

        buttons.forEach((button) => {
            if (button.getAttribute('aria-pressed') === 'true') {
                setSelected(button, true);
            }

            button.addEventListener('click', () => {
                const seatIds = (button.dataset.seatIds || '').split(',').filter(Boolean);
                if (button.dataset.seatType === 'couple' && seatIds.length !== 2) {
                    if (hint) hint.textContent = 'Cặp ghế đôi này hiện không khả dụng. Vui lòng chọn ghế khác.';
                    return;
                }

                const selectionKey = seatIds.join(',');
                const shouldSelect = !selected.has(selectionKey);
                setSelected(button, shouldSelect);
                refresh();

                if (hint && button.dataset.seatType === 'couple') {
                    hint.textContent = shouldSelect
                        ? `Đã chọn ghế đôi ${button.dataset.seatCode}.`
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
        let expiryReloadScheduled = false;

        const deadline = Date.parse(element.dataset.countdown || '');
        if (!Number.isFinite(deadline)) return;

        function refresh() {
            const remainingSeconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = remainingSeconds % 60;

            element.textContent = remainingSeconds > 0
                ? `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
                : (element.dataset.expiredLabel || 'Đã hết thời gian');

            if (remainingSeconds === 0) {
                window.clearInterval(intervalId);

                if (element.dataset.expiryReload === 'true' && !expiryReloadScheduled) {
                    expiryReloadScheduled = true;
                    document.querySelectorAll('[data-expiry-action]').forEach((control) => {
                        control.disabled = true;
                        control.setAttribute('aria-disabled', 'true');
                    });
                    window.setTimeout(() => window.location.reload(), 750);
                }
            }
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

if (document.documentElement.dataset.flashDismissInitialized !== 'true') {
    document.documentElement.dataset.flashDismissInitialized = 'true';
    document.addEventListener('click', (event) => {
        const dismissFlashButton = event.target.closest('[data-dismiss-flash]');
        dismissFlashButton?.closest('[data-flash-banner]')?.remove();
    });
}

const modalTriggers = new WeakMap();
const modalFocusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function openModal(modal, trigger) {
    if (!(modal instanceof HTMLElement)) return;

    modalTriggers.set(modal, trigger);
    modal.hidden = false;
    modal.classList.remove('hidden');
    modal.classList.add('grid');
    document.body.classList.add('overflow-hidden');
    const initialFocus = modal.querySelector('[data-modal-initial-focus]')
        || modal.querySelector(modalFocusableSelector)
        || modal.querySelector('[data-modal-panel]');
    window.requestAnimationFrame(() => initialFocus?.focus());
}

function closeModal(modal) {
    if (!(modal instanceof HTMLElement)) return;

    modal.hidden = true;
    modal.classList.add('hidden');
    modal.classList.remove('grid');
    document.body.classList.toggle('overflow-hidden', Boolean(document.querySelector('[data-modal]:not([hidden])')));
    const trigger = modalTriggers.get(modal);
    if (trigger instanceof HTMLElement) trigger.focus();
    modalTriggers.delete(modal);
}

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

        if (modalTrigger) openModal(modal, modalTrigger);
        else closeModal(modal);

        return;
    }

    const modalBackdrop = event.target.closest('[data-modal]');
    if (modalBackdrop && event.target === modalBackdrop) {
        closeModal(modalBackdrop);
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
    const openModalElement = document.querySelector('[data-modal]:not([hidden])');
    if (event.key === 'Tab' && openModalElement) {
        const focusable = Array.from(openModalElement.querySelectorAll(modalFocusableSelector))
            .filter((element) => element.getClientRects().length > 0);
        if (focusable.length === 0) {
            event.preventDefault();
            openModalElement.querySelector('[data-modal-panel]')?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
        return;
    }

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

    if (openModalElement) closeModal(openModalElement);
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
    if (canvases.length === 0) return;

    const { default: QRCode } = await import('qrcode');

    await Promise.all(Array.from(canvases, async (canvas) => {
        const value = canvas.dataset.qrValue;
        if (!value) return;

        const width = Number.parseInt(canvas.dataset.qrSize || '200', 10);
        await QRCode.toCanvas(canvas, value, {
            width,
            margin: 4,
            color: { dark: '#111827', light: '#ffffff' },
            errorCorrectionLevel: 'Q',
        });
    }));
}

renderTicketQrCodes().catch(() => {
    document.querySelectorAll('[data-qr-fallback]').forEach((element) => element.classList.remove('hidden'));
});
