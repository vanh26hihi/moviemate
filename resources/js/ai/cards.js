const money = new Intl.NumberFormat('vi-VN');

function node(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined && text !== null) element.textContent = String(text);
    return element;
}

function safeUrl(value) {
    if (typeof value !== 'string') return null;
    try {
        const url = new URL(value, window.location.origin);
        return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch { return null; }
}

function titleFor(card) {
    return card.type === 'showtime' ? card.movie_title : (card.title || card.name || 'MovieMate');
}

function factsFor(card) {
    if (card.type === 'movie') return [card.genres?.join(', '), card.duration_minutes ? `${card.duration_minutes} phút` : null, card.age_rating];
    if (card.type === 'showtime') return [`${card.date || ''} · ${card.time || ''}`, card.cinema?.name, card.starting_price_vnd !== undefined ? `Từ ${money.format(card.starting_price_vnd)} VNĐ` : null];
    if (card.type === 'cinema') return [card.address, [card.district, card.city].filter(Boolean).join(', '), card.phone];
    if (card.type === 'food') return [card.price_vnd !== undefined ? `${money.format(card.price_vnd)} VNĐ` : null, card.description];
    return [];
}

export function renderCards(cards, {historical = false} = {}) {
    const grid = node('div', 'ai-card-grid');
    (Array.isArray(cards) ? cards : []).filter((card) => ['movie', 'showtime', 'cinema', 'food'].includes(card?.type)).forEach((card) => {
        const article = node('article', 'ai-result-card');
        article.append(node('span', 'ai-card-kicker', {movie:'Phim',showtime:'Suất chiếu',cinema:'Rạp',food:'Đồ ăn'}[card.type]), node('h4', '', titleFor(card)));
        factsFor(card).filter(Boolean).slice(0, 4).forEach((fact) => article.append(node('p', '', fact)));
        if (card.reason) article.append(node('p', 'ai-card-reason', card.reason));
        if (!historical && Array.isArray(card.actions)) {
            const actions = node('div', 'ai-card-actions');
            card.actions.filter((action) => ['movie_details', 'view_showtimes', 'book_showtime'].includes(action?.type)).slice(0, 3).forEach((action) => {
                const url = safeUrl(action.url); if (!url) return;
                const link = node('a', action.type === 'book_showtime' ? 'ai-card-primary' : 'ai-card-link', action.label || 'Xem');
                link.href = url; actions.append(link);
            });
            if (actions.childElementCount) article.append(actions);
        }
        grid.append(article);
    });
    return grid;
}
