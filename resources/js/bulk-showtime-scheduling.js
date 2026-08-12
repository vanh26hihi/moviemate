function initializeBulkShowtimeWorkspace(workspace) {
    if (workspace.dataset.initialized === 'true') return;
    workspace.dataset.initialized = 'true';

    const form = workspace.querySelector('[data-bulk-showtime-form]');
    const rows = workspace.querySelector('[data-bulk-rows]');
    const template = workspace.querySelector('[data-bulk-row-template]');
    const addButton = workspace.querySelector('[data-bulk-add-row]');
    const previewButton = workspace.querySelector('[data-bulk-preview]');
    const publishButton = workspace.querySelector('[data-bulk-publish]');
    const message = workspace.querySelector('[data-bulk-message]');
    const total = workspace.querySelector('[data-summary-total]');
    const valid = workspace.querySelector('[data-summary-valid]');
    const invalid = workspace.querySelector('[data-summary-invalid]');
    let rowCounter = 0;
    let version = 0;
    let previewController = null;

    function setMessage(text, kind = 'neutral') {
        message.textContent = text;
        message.classList.toggle('text-success', kind === 'valid');
        message.classList.toggle('text-error', kind === 'invalid');
        message.classList.toggle('app-muted', kind === 'neutral' || kind === 'loading');
    }

    function updateNumbers() {
        rows.querySelectorAll('[data-bulk-row]').forEach((row, index) => {
            row.querySelector('[data-row-number]').textContent = index + 1;
        });
        total.textContent = rows.querySelectorAll('[data-bulk-row]').length;
    }

    function invalidatePreview() {
        version++;
        previewController?.abort();
        publishButton.disabled = true;
        valid.textContent = '0';
        invalid.textContent = '0';
        rows.querySelectorAll('[data-bulk-row]').forEach((row) => {
            const status = row.querySelector('[data-row-status]');
            status.textContent = 'Chưa kiểm tra';
            status.className = 'status-badge app-muted app-secondary';
            row.querySelector('[data-row-window]').textContent = '';
            row.querySelector('[data-row-error]').textContent = '';
        });
        setMessage('Dữ liệu đã thay đổi. Hãy kiểm tra lại toàn bộ lô trước khi đăng.');
        updateNumbers();
    }

    function addRow(values = {}) {
        const row = template.content.firstElementChild.cloneNode(true);
        row.dataset.rowKey = values.row_key || `row-${Date.now()}-${++rowCounter}`;
        row.querySelector('[data-row-movie]').value = values.movie_id || '';
        row.querySelector('[data-row-room]').value = values.room_id || '';
        row.querySelector('[data-row-date]').value = values.show_date || '';
        row.querySelector('[data-row-time]').value = values.show_time || '';
        row.querySelectorAll('select,input').forEach((input) => input.addEventListener('change', invalidatePreview));
        row.querySelector('[data-bulk-remove-row]').addEventListener('click', () => {
            row.remove();
            invalidatePreview();
        });
        rows.appendChild(row);
        invalidatePreview();
    }

    function payload() {
        return {
            rows: [...rows.querySelectorAll('[data-bulk-row]')].map((row) => ({
                row_key: row.dataset.rowKey,
                movie_id: row.querySelector('[data-row-movie]').value,
                room_id: row.querySelector('[data-row-room]').value,
                show_date: row.querySelector('[data-row-date]').value,
                show_time: row.querySelector('[data-row-time]').value,
            })),
        };
    }

    function renderResult(data, expectedVersion) {
        if (expectedVersion !== version) return;
        const resultByKey = new Map((data.rows || []).map((result) => [result.row_key, result]));
        rows.querySelectorAll('[data-bulk-row]').forEach((row) => {
            const result = resultByKey.get(row.dataset.rowKey);
            if (!result) return;
            const status = row.querySelector('[data-row-status]');
            status.textContent = result.valid ? 'Hợp lệ' : 'Không hợp lệ';
            status.className = result.valid ? 'status-badge text-success bg-success/10' : 'status-badge text-error bg-error/10';
            row.querySelector('[data-row-window]').textContent = result.window
                ? `${result.window.start_display} → ${result.window.end_display} · vệ sinh ${result.window.cleaning_display} · phòng sẵn sàng ${result.window.room_ready_display} · ${result.timezone}`
                : '';
            const internal = (result.internal_conflicts || []).map((item) => `dòng ${item.row_key} (${item.start_display}–${item.room_ready_display})`).join(', ');
            const persisted = result.conflict
                ? `${result.conflict.movie || 'Suất đã có'} · ${result.conflict.room_code || result.conflict.room || ''} (${result.conflict.start_display}–${result.conflict.room_ready_display})`
                : '';
            row.querySelector('[data-row-error]').textContent = result.valid ? '' : `${result.code || ''} · ${result.message || ''}${persisted ? ` Xung đột lịch hiện có: ${persisted}.` : ''}${internal ? ` Xung đột trong lô: ${internal}.` : ''}`;
        });
        total.textContent = data.summary?.total || 0;
        valid.textContent = data.summary?.valid_count || 0;
        invalid.textContent = data.summary?.invalid_count || 0;
        publishButton.disabled = !data.valid;
        setMessage(data.valid ? 'Toàn bộ lô hợp lệ tại thời điểm kiểm tra. Bạn có thể đăng toàn bộ.' : 'Lô còn dòng không hợp lệ. Hãy sửa hoặc xóa thủ công rồi kiểm tra lại.', data.valid ? 'valid' : 'invalid');
    }

    async function request(endpoint, body, signal) {
        return fetch(endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
            },
            body: JSON.stringify(body),
            signal,
        });
    }

    async function preview() {
        previewController?.abort();
        previewController = new AbortController();
        const expectedVersion = version;
        previewButton.disabled = true;
        publishButton.disabled = true;
        setMessage('Đang kiểm tra toàn bộ lô…', 'loading');
        try {
            const response = await request(workspace.dataset.previewEndpoint, payload(), previewController.signal);
            const data = await response.json();
            if (expectedVersion !== version) return;
            if (!response.ok) {
                setMessage(Object.values(data.errors || {}).flat()[0] || data.message || 'Dữ liệu lô không hợp lệ.', 'invalid');
                return;
            }
            renderResult(data, expectedVersion);
        } catch (error) {
            if (error.name !== 'AbortError' && expectedVersion === version) {
                setMessage('Không thể kiểm tra lô lúc này. Chưa có suất chiếu nào được đăng.', 'invalid');
            }
        } finally {
            if (expectedVersion === version) previewButton.disabled = false;
        }
    }

    async function publish(event) {
        event.preventDefault();
        if (publishButton.disabled) return;
        const expectedVersion = version;
        publishButton.disabled = true;
        previewButton.disabled = true;
        setMessage('Đang khóa phòng, kiểm tra lại và đăng toàn bộ lô…', 'loading');
        try {
            const response = await request(workspace.dataset.publishEndpoint, payload());
            const data = await response.json();
            if (expectedVersion !== version) return;
            if (!response.ok) {
                if (data.rows) renderResult(data, expectedVersion);
                setMessage(data.message || 'Lô không còn hợp lệ. Không có suất chiếu nào được tạo.', 'invalid');
                return;
            }
            setMessage(data.message, 'valid');
            window.location.assign(data.redirect);
        } catch {
            if (expectedVersion === version) setMessage('Không thể đăng lô lúc này. Không có xác nhận rằng suất chiếu đã được tạo; hãy tải lại lịch trước khi thử lại.', 'invalid');
        } finally {
            if (expectedVersion === version) previewButton.disabled = false;
        }
    }

    addButton.addEventListener('click', () => addRow());
    previewButton.addEventListener('click', preview);
    form.addEventListener('submit', publish);
    addRow();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bulk-showtime-workspace]').forEach(initializeBulkShowtimeWorkspace);
});
