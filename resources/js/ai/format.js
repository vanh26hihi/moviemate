function parseInlineFormatting(value) {
    const text = String(value ?? '');
    const tokens = [];
    let cursor = 0;

    while (cursor < text.length) {
        const opening = text.indexOf('**', cursor);
        if (opening < 0) {
            tokens.push({type: 'text', value: text.slice(cursor)});
            break;
        }

        if (opening > cursor) tokens.push({type: 'text', value: text.slice(cursor, opening)});
        const closing = text.indexOf('**', opening + 2);
        if (closing < 0) {
            tokens.push({type: 'text', value: text.slice(opening + 2)});
            break;
        }

        tokens.push({type: 'strong', value: text.slice(opening + 2, closing)});
        cursor = closing + 2;
    }

    return tokens;
}

export function parseAssistantText(value) {
    const blocks = [];
    let list = null;

    String(value ?? '').replace(/\r\n?/g, '\n').split('\n').forEach((line) => {
        const bullet = line.match(/^\s*[-*]\s+(.+)$/u);
        if (bullet) {
            if (!list) {
                list = {type: 'list', items: []};
                blocks.push(list);
            }
            list.items.push(parseInlineFormatting(bullet[1]));
            return;
        }

        list = null;
        if (line.trim() !== '') blocks.push({type: 'paragraph', content: parseInlineFormatting(line)});
    });

    return blocks;
}

function appendTokens(parent, tokens) {
    tokens.forEach((token) => {
        if (token.type === 'strong') {
            const strong = document.createElement('strong');
            strong.textContent = token.value;
            parent.append(strong);
        } else {
            parent.append(document.createTextNode(token.value));
        }
    });
}

export function renderAssistantText(container, value) {
    const fragment = document.createDocumentFragment();
    parseAssistantText(value).forEach((block) => {
        if (block.type === 'list') {
            const list = document.createElement('ul');
            block.items.forEach((tokens) => {
                const item = document.createElement('li');
                appendTokens(item, tokens);
                list.append(item);
            });
            fragment.append(list);
        } else {
            const paragraph = document.createElement('p');
            appendTokens(paragraph, block.content);
            fragment.append(paragraph);
        }
    });

    container.replaceChildren(fragment);
}
