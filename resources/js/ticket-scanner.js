import { BrowserQRCodeReader } from '@zxing/browser';

document.querySelectorAll('[data-ticket-scanner]').forEach((workspace) => {
    const video = workspace.querySelector('[data-scanner-video]');
    const start = workspace.querySelector('[data-scanner-start]');
    const stop = workspace.querySelector('[data-scanner-stop]');
    const error = workspace.querySelector('[data-scanner-error]');
    const input = workspace.querySelector('[data-scanner-input]');
    const form = input?.form;
    let controls;
    let submitting = false;

    const setError = (message = '') => {
        error.textContent = message;
        error.hidden = message === '';
    };

    const stopCamera = () => {
        controls?.stop();
        controls = undefined;
        start.hidden = false;
        stop.hidden = true;
    };

    start?.addEventListener('click', async () => {
        if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(window.location.hostname)) {
            setError('Camera chỉ hoạt động qua HTTPS hoặc localhost. Bạn vẫn có thể nhập mã thủ công.');
            return;
        }

        setError();
        try {
            const reader = new BrowserQRCodeReader(undefined, { delayBetweenScanAttempts: 250 });
            controls = await reader.decodeFromVideoDevice(undefined, video, (result) => {
                if (!result || submitting) return;
                submitting = true;
                input.value = result.getText();
                stopCamera();
                form?.requestSubmit();
            });
            start.hidden = true;
            stop.hidden = false;
        } catch (cameraError) {
            stopCamera();
            setError('Không thể mở camera. Hãy cấp quyền camera, kiểm tra thiết bị hoặc nhập mã thủ công.');
        }
    });

    stop?.addEventListener('click', stopCamera);
    window.addEventListener('pagehide', stopCamera, { once: true });
});
