export async function streamPost(url, payload, signal, onEvent) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const response = await fetch(url, {
        method: 'POST', signal,
        headers: {'Accept': 'text/event-stream', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token},
        body: JSON.stringify(payload),
    });
    if (!response.ok || !response.body) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data.message || `HTTP ${response.status}`);
    }
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    while (true) {
        const {value, done} = await reader.read();
        buffer += decoder.decode(value || new Uint8Array(), {stream: !done}).replace(/\r\n/g, '\n');
        let boundary;
        while ((boundary = buffer.indexOf('\n\n')) >= 0) {
            const block = buffer.slice(0, boundary); buffer = buffer.slice(boundary + 2);
            let event = 'message'; const data = [];
            block.split('\n').forEach((line) => {
                if (line.startsWith('event:')) event = line.slice(6).trim();
                if (line.startsWith('data:')) data.push(line.slice(5).trimStart());
            });
            if (data.length) onEvent(event, JSON.parse(data.join('\n')));
        }
        if (done) break;
    }
}
