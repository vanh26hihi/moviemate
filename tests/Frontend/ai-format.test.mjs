import assert from 'node:assert/strict';
import test from 'node:test';

import {parseAssistantText} from '../../resources/js/ai/format.js';

test('parses bold text without exposing Markdown markers', () => {
    assert.deepEqual(parseAssistantText('**Phim nổi bật**'), [
        {type: 'paragraph', content: [{type: 'strong', value: 'Phim nổi bật'}]},
    ]);
});

test('keeps malicious image markup inert inside the strong token', () => {
    assert.deepEqual(parseAssistantText('**<img src=x onerror=alert(1)>**'), [
        {type: 'paragraph', content: [{type: 'strong', value: '<img src=x onerror=alert(1)>'}]},
    ]);
});

test('keeps script markup as plain text and parses semantic bullets', () => {
    assert.deepEqual(parseAssistantText('<script>alert(1)</script>\n- Tra cứu phim\n* Tìm suất chiếu'), [
        {type: 'paragraph', content: [{type: 'text', value: '<script>alert(1)</script>'}]},
        {
            type: 'list',
            items: [
                [{type: 'text', value: 'Tra cứu phim'}],
                [{type: 'text', value: 'Tìm suất chiếu'}],
            ],
        },
    ]);
});
