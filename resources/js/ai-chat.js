import {streamPost} from './ai/sse';
import {renderCards} from './ai/cards';

const root = document.querySelector('[data-ai-assistant]');

if (root) {
    const q = (selector) => root.querySelector(selector);
    const panel = q('[data-ai-panel]');
    const overlay = q('[data-ai-overlay]');
    const launcher = q('[data-ai-launcher]');
    const messages = q('[data-ai-messages]');
    const welcome = q('[data-ai-welcome]');
    const form = q('[data-ai-form]');
    const input = q('[data-ai-input]');
    const send = q('[data-ai-send]');
    const status = q('[data-ai-status]');
    const historyView = q('[data-ai-history]');
    const chatView = q('[data-ai-chat]');
    const historyList = q('[data-ai-history-list]');
    const more = q('[data-ai-history-more]');
    const authenticated = root.dataset.authenticated === 'true';
    let currentConversation = null;
    let controller = null;
    let lastFocus = null;
    let historyPage = 1;
    let historyLastPage = 1;
    let pendingConversation = null;
    let pendingDialogTrigger = null;

    const el = (tag, className, text) => {
        const value = document.createElement(tag);
        if (className) value.className = className;
        if (text !== undefined) value.textContent = text;
        return value;
    };

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const json = async (url, options = {}) => {
        const response = await fetch(url, {headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()}, ...options});
        if (!response.ok) { const data = await response.json().catch(() => ({})); throw new Error(data.message || `HTTP ${response.status}`); }
        return response.status === 204 ? null : response.json();
    };

    function setStatus(text, announce = false) {
        status.setAttribute('aria-live', announce ? 'polite' : 'off');
        status.textContent = text;
    }

    function scrollEnd() { messages.scrollTop = messages.scrollHeight; }

    function bubble(role, text, cards = [], historical = false) {
        welcome.hidden = true;
        const row = el('div', `ai-message ai-message-${role}`);
        const body = el('div', 'ai-message-body', text);
        row.append(body);
        if (cards.length) row.append(renderCards(cards, {historical}));
        messages.append(row); scrollEnd();
        return {row, body};
    }

    function addRetry(row, retry) {
        const button = el('button', 'ai-retry-button', 'Thử lại');
        button.type = 'button';
        button.addEventListener('click', () => retry(button));
        row.append(button);
    }

    function clearChat() {
        messages.querySelectorAll('.ai-message').forEach((message) => message.remove());
        welcome.hidden = false; currentConversation = null;
        q('[data-ai-title]').textContent = 'MovieMate AI';
    }

    function openPanel() {
        lastFocus = document.activeElement;
        panel.hidden = false; overlay.hidden = false;
        document.body.classList.add('ai-panel-open');
        launcher.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(() => input.focus());
    }

    function closePanel() {
        controller?.abort(); panel.hidden = true; overlay.hidden = true;
        document.body.classList.remove('ai-panel-open'); launcher.setAttribute('aria-expanded', 'false');
        (lastFocus instanceof HTMLElement ? lastFocus : launcher).focus();
    }

    async function createConversation() {
        const result = await json(root.dataset.conversationsUrl, {method:'POST', body:'{}'});
        currentConversation = result.data.id; q('[data-ai-title]').textContent = result.data.title;
        return currentConversation;
    }

    async function sendMessage(message, retryUrl = null, existingRow = null) {
        if (controller) return;
        let userRow = existingRow;
        if (!retryUrl) userRow = bubble('user', message).row;
        const assistant = bubble('assistant', '');
        assistant.row.classList.add('is-streaming');
        panel.setAttribute('aria-busy', 'true'); send.disabled = true; input.disabled = true;
        setStatus('MovieMate đang suy nghĩ…');
        controller = new AbortController();
        let streamUrl = retryUrl;
        try {
            if (!streamUrl) {
                if (authenticated && !currentConversation) await createConversation();
                streamUrl = authenticated ? `/ai/conversations/${currentConversation}/messages/stream` : root.dataset.guestStreamUrl;
            }
            await streamPost(streamUrl, retryUrl && authenticated ? {} : {message}, controller.signal, (event, envelope) => {
                const data = envelope?.data || {};
                if (event === 'conversation') {
                    currentConversation = data.conversation_id;
                    q('[data-ai-title]').textContent = data.title || 'MovieMate AI';
                    assistant.row.dataset.retryUrl = data.retry_url || '';
                } else if (event === 'text_delta') {
                    assistant.body.append(document.createTextNode(data.delta || '')); scrollEnd();
                } else if (event === 'cards') {
                    assistant.row.append(renderCards(data.cards || [])); scrollEnd();
                } else if (event === 'error') {
                    throw new Error(data.message || 'Không thể nhận phản hồi.');
                } else if (event === 'completed') {
                    setStatus('Đã nhận phản hồi.', true);
                }
            });
            if (!assistant.body.textContent.trim()) throw new Error('Phản hồi bị gián đoạn.');
            assistant.row.classList.remove('is-streaming');
        } catch (error) {
            if (error.name === 'AbortError') { assistant.row.remove(); return; }
            assistant.body.textContent = 'MovieMate AI tạm thời không thể trả lời. Vui lòng kiểm tra kết nối và thử lại.';
            assistant.row.classList.remove('is-streaming'); assistant.row.classList.add('is-error');
            setStatus('Phản hồi bị gián đoạn. Bạn có thể thử lại.', true);
            const authRetry = assistant.row.dataset.retryUrl;
            addRetry(assistant.row, async (button) => {
                button.remove(); assistant.row.remove();
                await sendMessage(message, authenticated ? authRetry : root.dataset.guestRetryUrl, userRow);
            });
        } finally {
            controller = null; panel.removeAttribute('aria-busy'); send.disabled = false; input.disabled = false; input.focus(); scrollEnd();
        }
    }

    async function loadConversation(id) {
        const result = await json(`/ai/conversations/${id}`);
        clearChat(); currentConversation = result.data.id; q('[data-ai-title]').textContent = result.data.title;
        result.data.messages.forEach((message) => bubble(message.role, message.content, message.historical_cards || [], true));
        showChat(); input.focus();
    }

    function historyItem(conversation) {
        const item = el('div', 'ai-history-item');
        const open = el('button', 'ai-history-open'); open.type = 'button';
        open.append(el('strong', '', conversation.title), el('span', '', conversation.last_message_at ? new Date(conversation.last_message_at).toLocaleString('vi-VN') : 'Chưa có tin nhắn'));
        open.addEventListener('click', () => loadConversation(conversation.id).catch(showError));
        const rename = el('button', 'ai-history-action', 'Đổi tên'); rename.type = 'button';
        const remove = el('button', 'ai-history-action', 'Xóa'); remove.type = 'button';
        rename.addEventListener('click', () => openDialog('rename', conversation, rename));
        remove.addEventListener('click', () => openDialog('delete', conversation, remove));
        const actions = el('div', 'ai-history-actions'); actions.append(rename, remove); item.append(open, actions); return item;
    }

    async function loadHistory(reset = false) {
        if (reset) { historyPage = 1; historyList.replaceChildren(); }
        const result = await json(`${root.dataset.conversationsUrl}?page=${historyPage}`);
        result.data.forEach((conversation) => historyList.append(historyItem(conversation)));
        historyLastPage = result.last_page; more.hidden = historyPage >= historyLastPage;
        if (!historyList.childElementCount) historyList.append(el('p', 'ai-empty', 'Bạn chưa có cuộc trò chuyện nào.'));
    }

    function showHistory() { chatView.hidden = true; historyView.hidden = false; loadHistory(true).catch(showError); }
    function showChat() { historyView.hidden = true; chatView.hidden = false; }
    function showError(error) { setStatus(error.message || 'Đã có lỗi xảy ra.', true); showChat(); }

    function openDialog(kind, conversation, trigger) {
        pendingConversation = conversation; pendingDialogTrigger = trigger;
        const dialog = q(`[data-ai-${kind}-dialog]`); dialog.hidden = false;
        if (kind === 'rename') { q('[data-ai-rename-input]').value = conversation.title; q('[data-ai-rename-input]').focus(); }
        else q('[data-ai-delete-confirm]').focus();
    }

    function closeDialogs() {
        root.querySelectorAll('.ai-nested-dialog').forEach((dialog) => { dialog.hidden = true; });
        pendingConversation = null; pendingDialogTrigger?.focus(); pendingDialogTrigger = null;
    }

    launcher.addEventListener('click', openPanel); q('[data-ai-close]').addEventListener('click', closePanel); overlay.addEventListener('click', closePanel);
    q('[data-ai-history-toggle]')?.addEventListener('click', showHistory); q('[data-ai-history-back]')?.addEventListener('click', showChat);
    q('[data-ai-new]')?.addEventListener('click', () => { clearChat(); showChat(); input.focus(); });
    more?.addEventListener('click', () => { if (historyPage < historyLastPage) { historyPage += 1; loadHistory().catch(showError); } });
    root.querySelectorAll('[data-ai-prompt]').forEach((button) => button.addEventListener('click', () => sendMessage(button.dataset.aiPrompt)));
    form.addEventListener('submit', (event) => { event.preventDefault(); const message = input.value.trim(); if (!message) return; input.value = ''; sendMessage(message); });
    input.addEventListener('keydown', (event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); } });
    root.querySelectorAll('[data-ai-dialog-cancel]').forEach((button) => button.addEventListener('click', closeDialogs));
    q('[data-ai-rename-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault(); const title = q('[data-ai-rename-input]').value.trim(); if (!title || !pendingConversation) return;
        await json(`/ai/conversations/${pendingConversation.id}`, {method:'PATCH',body:JSON.stringify({title})}).then(() => { closeDialogs(); loadHistory(true); }).catch(showError);
    });
    q('[data-ai-delete-confirm]')?.addEventListener('click', async () => {
        if (!pendingConversation) return; const id = pendingConversation.id;
        await json(`/ai/conversations/${id}`, {method:'DELETE'}).then(() => { if (currentConversation === id) clearChat(); closeDialogs(); loadHistory(true); }).catch(showError);
    });
    document.addEventListener('keydown', (event) => {
        if (panel.hidden) return;
        const nested = [...root.querySelectorAll('.ai-nested-dialog')].find((dialog) => !dialog.hidden);
        if (event.key === 'Escape') { event.preventDefault(); nested ? closeDialogs() : closePanel(); return; }
        if (event.key !== 'Tab') return;
        const scope = nested || panel; const focusable = [...scope.querySelectorAll('button:not([disabled]),a[href],textarea:not([disabled]),input:not([disabled])')].filter((item) => !item.closest('[hidden]'));
        if (!focusable.length) return; const first = focusable[0]; const last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
}
