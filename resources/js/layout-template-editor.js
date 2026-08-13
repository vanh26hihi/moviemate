function initializeLayoutTemplateEditor(form) {
    if (!(form instanceof HTMLFormElement) || form.dataset.layoutTemplateEditorInitialized === 'true') return;
    form.dataset.layoutTemplateEditorInitialized = 'true';

    const seedElement = form.querySelector('[data-layout-template-seed]');
    const rowsInput = form.querySelector('[data-layout-rows]');
    const columnsInput = form.querySelector('[data-layout-columns]');
    const screenInput = form.querySelector('[data-layout-screen]');
    const grid = form.querySelector('[data-layout-grid]');
    const stage = form.querySelector('[data-layout-stage]');
    const screen = form.querySelector('[data-layout-screen-visual]');
    const hiddenInput = form.querySelector('[data-layout-json]');
    const message = form.querySelector('[data-layout-editor-message]');
    if (!(seedElement instanceof HTMLScriptElement)
        || !(rowsInput instanceof HTMLInputElement)
        || !(columnsInput instanceof HTMLInputElement)
        || !(screenInput instanceof HTMLSelectElement)
        || !(grid instanceof HTMLElement)
        || !(stage instanceof HTMLElement)
        || !(screen instanceof HTMLElement)
        || !(hiddenInput instanceof HTMLInputElement)) return;

    let seed;
    try {
        seed = JSON.parse(seedElement.textContent || '{}');
    } catch (error) {
        return;
    }

    const cells = new Map((Array.isArray(seed.cells) ? seed.cells : []).map((cell) => [
        `${cell.x_position}:${cell.y_position}`,
        cell,
    ]));
    let activeTool = 'normal';

    function rowLabel(index) {
        let label = '';
        while (index > 0) {
            index -= 1;
            label = String.fromCharCode(65 + (index % 26)) + label;
            index = Math.floor(index / 26);
        }
        return label;
    }

    function dimensions() {
        return {
            rows: Math.max(1, Number.parseInt(rowsInput.value, 10) || 1),
            columns: Math.max(1, Number.parseInt(columnsInput.value, 10) || 1),
        };
    }

    function pairMembers(pairKey) {
        return Array.from(cells.entries()).filter(([, cell]) => cell.pair_key === pairKey);
    }

    function clearCell(key) {
        const current = cells.get(key);
        if (current?.seat_type === 'couple' && current.pair_key) {
            pairMembers(current.pair_key).forEach(([memberKey]) => cells.delete(memberKey));
            return;
        }
        cells.delete(key);
    }

    function cellLabel(cell, x, y) {
        const coordinate = `${rowLabel(y)}${x}`;
        if (!cell) return `Ô ${coordinate}, trống`;
        if (cell.cell_type === 'aisle') return `Ô ${coordinate}, lối đi`;
        if (cell.cell_type === 'blocked') return `Ô ${coordinate}, vật cản cố định, vị trí cấu trúc không bố trí ghế`;
        if (cell.seat_type === 'couple') {
            const labels = pairMembers(cell.pair_key).map(([, member]) => member.seat_label).sort();
            return `Ô ${labels.join('-') || coordinate}, ghế đôi`;
        }
        return `Ô ${cell.seat_label || coordinate}, ${cell.seat_type === 'vip' ? 'ghế VIP' : 'ghế thường'}`;
    }

    function cellClass(cell, x) {
        if (!cell) return 'is-empty';
        if (cell.cell_type === 'aisle') return 'is-aisle';
        if (cell.cell_type === 'blocked') return 'is-blocked';
        if (cell.seat_type !== 'couple') return cell.seat_type === 'vip' ? 'is-vip' : 'is-normal';
        const members = pairMembers(cell.pair_key).map(([, member]) => member).sort((a, b) => a.x_position - b.x_position);
        return `is-couple ${members[0]?.x_position === x ? 'is-couple-left' : 'is-couple-right'}`;
    }

    function updateStatistics(rows, columns) {
        const visibleCells = Array.from(cells.values()).filter((cell) => cell.x_position <= columns && cell.y_position <= rows);
        const normal = visibleCells.filter((cell) => cell.cell_type === 'seat' && cell.seat_type === 'normal').length;
        const vip = visibleCells.filter((cell) => cell.cell_type === 'seat' && cell.seat_type === 'vip').length;
        const couplePositions = visibleCells.filter((cell) => cell.cell_type === 'seat' && cell.seat_type === 'couple').length;
        const couplePairs = new Set(visibleCells.filter((cell) => cell.pair_key).map((cell) => cell.pair_key)).size;
        const aisles = visibleCells.filter((cell) => cell.cell_type === 'aisle').length;
        const blocked = visibleCells.filter((cell) => cell.cell_type === 'blocked').length;
        const values = {
            physical_seats: normal + vip + couplePositions,
            pricing_units: normal + vip + couplePairs,
            normal,
            vip,
            couple: `${couplePairs} cặp`,
            aisle: `${aisles} ô`,
            blocked: `${blocked} ô`,
            dimensions: `${rows} × ${columns}`,
        };
        Object.entries(values).forEach(([key, value]) => {
            const element = form.querySelector(`[data-layout-stat="${key}"]`);
            if (element) element.textContent = String(value);
        });
        const positions = form.querySelector('[data-layout-couple-positions]');
        if (positions) positions.textContent = `${couplePositions} vị trí`;

        const hiddenCells = Array.from(cells.values()).filter((cell) => cell.x_position > columns || cell.y_position > rows).length;
        if (message) {
            message.classList.toggle('hidden', hiddenCells === 0);
            message.textContent = hiddenCells > 0
                ? `${hiddenCells} ô đã bố trí nằm ngoài kích thước hiện tại. Tăng lại hàng/cột hoặc xóa các ô đó trước khi lưu.`
                : '';
        }
    }

    function sync() {
        const { rows, columns } = dimensions();
        hiddenInput.value = JSON.stringify({
            rows,
            columns,
            screen_position: screenInput.value,
            cells: Array.from(cells.values()),
        });
    }

    function render() {
        const { rows, columns } = dimensions();
        rowsInput.value = String(rows);
        columnsInput.value = String(columns);
        grid.style.gridTemplateColumns = `repeat(${columns}, 3rem)`;
        grid.replaceChildren();

        for (let y = 1; y <= rows; y += 1) {
            for (let x = 1; x <= columns; x += 1) {
                const key = `${x}:${y}`;
                const cell = cells.get(key);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `layout-template-seat ${cellClass(cell, x)}`;
                button.dataset.coordinate = key;
                button.setAttribute('role', 'gridcell');
                button.setAttribute('aria-label', cellLabel(cell, x, y));
                button.title = cellLabel(cell, x, y);
                if (cell?.cell_type === 'aisle') {
                    const icon = document.createElement('i');
                    icon.className = 'ph ph-arrows-down-up';
                    icon.setAttribute('aria-hidden', 'true');
                    button.appendChild(icon);
                } else if (cell?.cell_type === 'blocked') {
                    const icon = document.createElement('i');
                    icon.className = 'ph ph-bricks';
                    icon.setAttribute('aria-hidden', 'true');
                    button.appendChild(icon);
                } else if (cell) {
                    button.textContent = cell.seat_label || '';
                    if (cell.seat_type === 'vip') {
                        const accent = document.createElement('span');
                        accent.className = 'layout-template-seat-accent';
                        accent.textContent = '★';
                        accent.setAttribute('aria-hidden', 'true');
                        button.appendChild(accent);
                    }
                }
                button.addEventListener('click', () => paint(x, y, rows, columns));
                grid.appendChild(button);
            }
        }

        screen.setAttribute('aria-label', `Màn hình ở phía ${screenInput.value === 'top' ? 'trên' : 'dưới'}`);
        if (screenInput.value === 'top') stage.prepend(screen);
        else stage.append(screen);
        updateStatistics(rows, columns);
        sync();
    }

    function paint(x, y, rows, columns) {
        const key = `${x}:${y}`;
        if (activeTool === 'empty') {
            clearCell(key);
        } else if (activeTool === 'aisle') {
            clearCell(key);
            cells.set(key, { x_position: x, y_position: y, cell_type: 'aisle' });
        } else if (activeTool === 'blocked') {
            clearCell(key);
            cells.set(key, { x_position: x, y_position: y, cell_type: 'blocked' });
        } else if (activeTool === 'couple') {
            if (x >= columns) {
                if (message) {
                    message.textContent = 'Ghế đôi cần một ô liền bên phải trong cùng hàng.';
                    message.classList.remove('hidden');
                }
                return;
            }
            const rightKey = `${x + 1}:${y}`;
            clearCell(key);
            clearCell(rightKey);
            const pairKey = `PAIR-${y}-${x}`;
            cells.set(key, { x_position: x, y_position: y, cell_type: 'seat', seat_type: 'couple', seat_label: `${rowLabel(y)}${x}`, pair_key: pairKey });
            cells.set(rightKey, { x_position: x + 1, y_position: y, cell_type: 'seat', seat_type: 'couple', seat_label: `${rowLabel(y)}${x + 1}`, pair_key: pairKey });
        } else {
            clearCell(key);
            cells.set(key, { x_position: x, y_position: y, cell_type: 'seat', seat_type: activeTool, seat_label: `${rowLabel(y)}${x}` });
        }
        render();
    }

    function selectTool(button) {
        activeTool = button.dataset.layoutTool || 'normal';
        form.querySelectorAll('[data-layout-tool]').forEach((candidate) => {
            const selected = candidate === button;
            candidate.setAttribute('aria-pressed', String(selected));
            candidate.classList.toggle('is-active', selected);
            candidate.querySelector('[data-tool-check]')?.classList.toggle('invisible', !selected);
        });
    }

    form.querySelectorAll('[data-layout-tool]').forEach((button) => {
        button.addEventListener('click', () => selectTool(button));
    });
    [rowsInput, columnsInput, screenInput].forEach((input) => input.addEventListener('change', render));
    form.addEventListener('submit', sync);
    render();
}

document.querySelectorAll('[data-layout-template-form]').forEach(initializeLayoutTemplateEditor);
