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

function safeMediaUrl(value) {
    if (typeof value !== 'string') return null;
    try {
        const url = new URL(value, window.location.origin);
        return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
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
        const title = titleFor(card);
        const article = node('article', `ai-result-card ai-result-card-${card.type}`);
        const content = node('div', 'ai-card-content');
        const mediaUrl = safeMediaUrl(card.type === 'movie' ? card.poster_url : card.image_url);

        if (card.type === 'movie' || mediaUrl) {
            const media = node('div', `ai-card-media ai-card-media-${card.type}`);
            const fallback = node('div', 'ai-card-media-fallback');
            fallback.setAttribute('role', 'img');
            fallback.setAttribute('aria-label', card.type === 'movie' ? `Chưa có poster cho ${title}` : `Chưa có hình ảnh cho ${title}`);
            const fallbackIcon = node('i', card.type === 'movie' ? 'ph-fill ph-film-slate' : 'ph-fill ph-image-square');
            fallbackIcon.setAttribute('aria-hidden', 'true');
            fallback.append(fallbackIcon, node('span', '', card.type === 'movie' ? 'MovieMate' : 'Chưa có ảnh'));

            if (mediaUrl) {
                const image = node('img');
                image.src = mediaUrl;
                image.alt = card.type === 'movie' ? `Poster ${title}` : `Hình ảnh ${title}`;
                image.loading = 'lazy';
                image.decoding = 'async';
                fallback.hidden = true;
                image.addEventListener('error', () => { image.remove(); fallback.hidden = false; }, {once: true});
                media.append(image);
            }
            media.append(fallback);
            article.classList.add('has-media');
            article.append(media);
        }

        content.append(node('span', 'ai-card-kicker', {movie:'Phim',showtime:'Suất chiếu',cinema:'Rạp',food:'Đồ ăn'}[card.type]), node('h4', '', title));
        factsFor(card).filter(Boolean).slice(0, 4).forEach((fact) => content.append(node('p', '', fact)));
        if (card.reason) content.append(node('p', 'ai-card-reason', card.reason));
        if (!historical && Array.isArray(card.actions)) {
            const actions = node('div', 'ai-card-actions');
            card.actions.filter((action) => ['movie_details', 'view_showtimes', 'book_showtime'].includes(action?.type)).slice(0, 3).forEach((action) => {
                const url = safeUrl(action.url); if (!url) return;
                const link = node('a', action.type === 'book_showtime' ? 'ai-card-primary' : 'ai-card-link', action.label || 'Xem');
                link.href = url; actions.append(link);
            });
            if (actions.childElementCount) content.append(actions);
        }
        article.append(content);
        grid.append(article);
    });
    return grid;
}
