import './form-validation';

const printNow = () => {
    window.print();
};

if (!window.__moviemateStaffPrintBound) {
    document.querySelector('[data-staff-print-trigger]')?.addEventListener('click', printNow);
    window.__moviemateStaffPrintBound = true;
}

const operationId = document.body?.dataset.printOperationId;
if (operationId) {
    const key = `moviemate:staff-print-dialog:${operationId}`;
    let shouldOpen = true;
    try {
        shouldOpen = sessionStorage.getItem(key) !== 'opened';
        sessionStorage.setItem(key, 'opened');
    } catch {
        // The manual button remains available when browser storage is blocked.
        shouldOpen = false;
    }
    if (shouldOpen) {
        window.setTimeout(printNow, 250);
    }
}
