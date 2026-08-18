const REMOTE_DEBOUNCE_MS = 450;
const fieldTimers = new WeakMap();
const fieldRequests = new WeakMap();
const remoteCache = new Map();
let generatedErrorId = 0;

function mutationForms() {
    return Array.from(document.querySelectorAll('.admin-shell form, .staff-shell form, form[data-inline-validation]'))
        .filter((form) => (form.getAttribute('method') || 'GET').toUpperCase() !== 'GET');
}

function controlsFor(form) {
    return Array.from(form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea'));
}

function fieldKey(control) {
    return control.dataset.validationKey || control.name.replace(/\[\]$/, '');
}

function fieldLabel(control) {
    const explicit = control.getAttribute('aria-label');
    const label = control.id ? document.querySelector(`label[for="${CSS.escape(control.id)}"]`) : control.closest('label');
    const nestedLabel = label?.querySelector(':scope > .admin-label, :scope > .cinema-label');
    const directText = Array.from(label?.childNodes || [])
        .filter((node) => node.nodeType === Node.TEXT_NODE)
        .map((node) => node.textContent)
        .join(' ');
    const text = explicit || nestedLabel?.textContent || directText || label?.textContent || fieldKey(control).replaceAll('_', ' ');

    return text.replace(/\s*\*\s*$/, '').replace(/\s+/g, ' ').trim().toLocaleLowerCase('vi');
}

function controlsInGroup(control) {
    if (!['checkbox', 'radio'].includes(control.type) || !control.name) return [control];

    return Array.from(control.form?.elements || []).filter((candidate) => candidate.name === control.name);
}

function errorHost(control) {
    const label = control.closest('label');
    if (label && label.contains(control)) return label;

    return control;
}

function baseDescriptions(control) {
    if (control.dataset.validationBaseDescriptions === undefined) {
        control.dataset.validationBaseDescriptions = control.getAttribute('aria-describedby') || '';
    }

    return control.dataset.validationBaseDescriptions.split(/\s+/).filter(Boolean);
}

function findExistingError(control, message) {
    const key = fieldKey(control);
    const expectedId = control.id ? `${control.id}-error` : '';
    const byId = expectedId ? document.getElementById(expectedId) : null;
    if (byId) return byId;

    const scope = control.closest('fieldset, label, div') || control.parentElement;
    return Array.from(scope?.querySelectorAll('[role="alert"], .text-error') || [])
        .find((candidate) => candidate.textContent.replace(/\s+/g, ' ').trim() === message)
        || control.form?.querySelector(`[data-validation-error-for="${CSS.escape(key)}"]`);
}

function setError(control, message, source = 'local') {
    if (!message) return;

    const key = fieldKey(control);
    let error = findExistingError(control, message);
    if (!error) {
        error = document.createElement('p');
        error.className = 'form-validation-error';
        error.setAttribute('role', 'alert');
        error.dataset.validationGenerated = 'true';
        errorHost(control).insertAdjacentElement('afterend', error);
    }
    if (!error.id) {
        generatedErrorId += 1;
        error.id = `${control.id || `validation-field-${generatedErrorId}`}-error`;
    }
    error.dataset.validationErrorFor = key;
    error.dataset.validationSource = source;
    error.textContent = message;
    error.hidden = false;

    controlsInGroup(control).forEach((candidate) => {
        candidate.setAttribute('aria-invalid', 'true');
        const descriptions = [...baseDescriptions(candidate).filter((id) => id !== error.id), error.id];
        candidate.setAttribute('aria-describedby', descriptions.join(' '));
    });
}

function clearError(control, sources = null) {
    const error = control.form?.querySelector(`[data-validation-error-for="${CSS.escape(fieldKey(control))}"]`);
    if (!error || (sources && !sources.includes(error.dataset.validationSource))) return;

    error.hidden = true;
    error.textContent = '';
    controlsInGroup(control).forEach((candidate) => {
        candidate.removeAttribute('aria-invalid');
        const descriptions = baseDescriptions(candidate);
        if (descriptions.length) candidate.setAttribute('aria-describedby', descriptions.join(' '));
        else candidate.removeAttribute('aria-describedby');
    });
}

function dependentRequired(control) {
    const definition = control.dataset.validationRequiredIf;
    if (!definition) return false;

    const [dependency, expected] = definition.split(':', 2);
    const related = control.form?.elements.namedItem(dependency);
    return related && related.value === expected;
}

function isBlank(control) {
    if (control.type === 'checkbox' || control.type === 'radio') {
        return !controlsInGroup(control).some((candidate) => candidate.checked);
    }

    return control.value.trim() === '';
}

function localMessage(control) {
    if (control.disabled || control.readOnly || !control.name) return '';

    const label = fieldLabel(control);
    if (dependentRequired(control) && isBlank(control)) return `Vui lòng nhập ${label}.`;
    if (control.validity.valid) return '';
    if (control.validity.valueMissing) return control instanceof HTMLSelectElement
        ? `Vui lòng chọn ${label}.`
        : `Vui lòng nhập ${label}.`;
    if (control.validity.typeMismatch) return `${label} chưa đúng định dạng.`;
    if (control.validity.badInput) return `${label} phải là số hợp lệ.`;
    if (control.validity.patternMismatch) return `Định dạng ${label} chưa đúng.`;
    if (control.validity.tooShort) return `${label} phải có ít nhất ${control.minLength} ký tự.`;
    if (control.validity.tooLong) return `${label} không được dài hơn ${control.maxLength} ký tự.`;
    if (control.validity.rangeUnderflow) return `${label} phải từ ${control.min} trở lên.`;
    if (control.validity.rangeOverflow) return `${label} không được lớn hơn ${control.max}.`;
    if (control.validity.stepMismatch) return `${label} chưa đúng bước giá trị cho phép.`;

    return `${label} chưa hợp lệ.`;
}

function validateLocal(control) {
    const message = localMessage(control);
    if (message) {
        setError(control, message, 'local');
        return false;
    }

    clearError(control, ['local']);
    return true;
}

function serverErrorEntries() {
    const errors = {};
    document.querySelectorAll('[data-form-validation-errors]').forEach((state) => {
        try {
            const encoded = (state.textContent || '').trim();
            const bytes = Uint8Array.from(window.atob(encoded), (character) => character.charCodeAt(0));
            Object.assign(errors, JSON.parse(new TextDecoder().decode(bytes)));
        } catch {
            // The authoritative summary remains visible if embedded state is malformed.
        }
    });

    return Object.entries(errors);
}

function matchingControl(field) {
    for (const form of mutationForms()) {
        const controls = controlsFor(form);
        const exact = controls.find((control) => fieldKey(control) === field);
        if (exact) return exact;
        const root = field.split('.')[0];
        const grouped = controls.find((control) => fieldKey(control) === root || control.name === `${root}[]`);
        if (grouped) return grouped;
    }

    return null;
}

function applyServerErrors() {
    let firstInvalid = null;
    serverErrorEntries().forEach(([field, messages]) => {
        const control = matchingControl(field);
        const message = Array.isArray(messages) ? messages[0] : messages;
        if (!control || !message) return;
        control.dataset.validationTouched = 'true';
        setError(control, String(message), 'server');
        firstInvalid ||= control;
    });

    if (firstInvalid) {
        window.requestAnimationFrame(() => {
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
}

function remotePayload(control) {
    const payload = {
        rule: control.dataset.validationRule,
        value: control.value,
    };
    if (control.dataset.validationRecord) payload.record_id = Number(control.dataset.validationRecord);
    const cinemaField = control.dataset.validationCinemaField;
    if (cinemaField) {
        const cinema = control.form?.elements.namedItem(cinemaField);
        if (cinema?.value) payload.cinema_id = Number(cinema.value);
    }

    return payload;
}

async function validateRemote(control) {
    if (!control.dataset.validationRule || !control.dataset.validationUrl || !validateLocal(control) || isBlank(control)) return;

    const payload = remotePayload(control);
    const cacheKey = `${control.dataset.validationUrl}|${JSON.stringify(payload)}`;
    const cached = remoteCache.get(cacheKey);
    if (cached) {
        if (cached.valid) clearError(control, ['remote', 'server']);
        else setError(control, cached.message, 'remote');
        return;
    }

    fieldRequests.get(control)?.abort();
    const request = new AbortController();
    fieldRequests.set(control, request);
    control.setAttribute('aria-busy', 'true');
    const requestedValue = control.value;

    try {
        const response = await fetch(control.dataset.validationUrl, {
            method: 'POST',
            credentials: 'same-origin',
            signal: request.signal,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(payload),
        });
        const result = await response.json().catch(() => ({}));
        if (request.signal.aborted || control.value !== requestedValue) return;

        if (response.ok && result.valid === true) {
            remoteCache.set(cacheKey, { valid: true });
            clearError(control, ['remote', 'server']);
        } else if (response.status === 422 && result.message) {
            const validationResult = { valid: false, message: String(result.message) };
            remoteCache.set(cacheKey, validationResult);
            setError(control, validationResult.message, 'remote');
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            // Submit validation remains authoritative when a realtime request is unavailable.
        }
    } finally {
        if (fieldRequests.get(control) === request) {
            fieldRequests.delete(control);
            control.removeAttribute('aria-busy');
        }
    }
}

function scheduleRemote(control, immediate = false) {
    window.clearTimeout(fieldTimers.get(control));
    if (!control.dataset.validationRule) return;

    if (immediate) {
        validateRemote(control);
        return;
    }
    fieldTimers.set(control, window.setTimeout(() => validateRemote(control), REMOTE_DEBOUNCE_MS));
}

function initializeForm(form) {
    if (form.dataset.validationInitialized === 'true') return;
    form.dataset.validationInitialized = 'true';
    form.noValidate = true;

    controlsFor(form).forEach((control) => {
        control.addEventListener('blur', () => {
            control.dataset.validationTouched = 'true';
            validateLocal(control);
            scheduleRemote(control, true);
        });
        control.addEventListener('input', () => {
            control.dataset.validationTouched = 'true';
            if (validateLocal(control)) {
                clearError(control, ['server', 'remote']);
                scheduleRemote(control);
            }
        });
        control.addEventListener('change', () => {
            control.dataset.validationTouched = 'true';
            if (validateLocal(control)) {
                clearError(control, ['server', 'remote']);
                scheduleRemote(control, true);
            }
            controlsFor(form).filter((candidate) => candidate.dataset.validationRequiredIf).forEach((candidate) => {
                if (candidate.dataset.validationTouched === 'true') validateLocal(candidate);
            });
        });
    });

    form.addEventListener('submit', (event) => {
        const invalid = controlsFor(form).filter((control) => {
            control.dataset.validationTouched = 'true';
            return !validateLocal(control);
        });
        const knownInvalid = controlsFor(form).find((control) => control.getAttribute('aria-invalid') === 'true');
        const firstInvalid = invalid[0] || knownInvalid;
        if (!firstInvalid) return;

        event.preventDefault();
        firstInvalid.focus({ preventScroll: true });
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

export function initializeFormValidation() {
    mutationForms().forEach(initializeForm);
    applyServerErrors();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFormValidation, { once: true });
} else {
    initializeFormValidation();
}
