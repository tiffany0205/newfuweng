const CATEGORY_NAMES = {
    safe: '安全格',
    landmark: '紫色地标',
    boost: '增益格',
    risk: '风险格',
    reward: '奖励格',
};

export function positionCellInspector(anchor, panel, viewport) {
    const gap = viewport.gap ?? 10;
    const padding = viewport.padding ?? 12;
    const anchorCenter = anchor.left + (anchor.width / 2);
    const maxLeft = viewport.width - panel.width - padding;
    const left = Math.max(padding, Math.min(anchorCenter - (panel.width / 2), maxLeft));
    const placement = anchor.top >= panel.height + gap + padding ? 'above' : 'below';
    const top = placement === 'above' ? anchor.top - panel.height - gap : anchor.bottom + gap;
    const arrowOffset = Math.max(10, Math.min(anchorCenter - left, panel.width));

    return { left, top, placement, arrowOffset };
}

export function initCellInspector(documentRef = globalThis.document, windowRef = globalThis.window) {
    const inspector = documentRef.querySelector('[data-cell-inspector]');
    if (!inspector) return false;

    const cells = [...documentRef.querySelectorAll('.cell')];
    const closeButton = inspector.querySelector('.inspector-close');
    const hoverQuery = windowRef.matchMedia('(hover: hover) and (pointer: fine)');
    let activeCell = null;
    let closeTimer = null;

    const clearClose = () => {
        if (closeTimer) windowRef.clearTimeout(closeTimer);
        closeTimer = null;
    };

    const place = () => {
        if (!activeCell || !hoverQuery.matches || inspector.hidden) return;
        const position = positionCellInspector(
            activeCell.getBoundingClientRect(),
            { width: inspector.offsetWidth, height: inspector.offsetHeight },
            { width: windowRef.innerWidth, height: windowRef.innerHeight, gap: 10, padding: 12 },
        );
        inspector.style.left = `${position.left}px`;
        inspector.style.top = `${position.top}px`;
        inspector.style.setProperty('--inspector-arrow', `${position.arrowOffset}px`);
        inspector.dataset.placement = position.placement;
    };

    const hide = () => {
        clearClose();
        if (activeCell) {
            activeCell.classList.remove('is-inspected');
            activeCell.setAttribute('aria-expanded', 'false');
        }
        activeCell = null;
        inspector.hidden = true;
    };

    const scheduleHide = () => {
        clearClose();
        closeTimer = windowRef.setTimeout(hide, 150);
    };

    const show = (cell) => {
        clearClose();
        if (activeCell && activeCell !== cell) {
            activeCell.classList.remove('is-inspected');
            activeCell.setAttribute('aria-expanded', 'false');
        }
        activeCell = cell;
        const landmark = cell.dataset.category === 'landmark';
        inspector.querySelector('.inspector-type').textContent = CATEGORY_NAMES[cell.dataset.category] || '棋盘格';
        inspector.querySelector('.inspector-icon').textContent = cell.querySelector('.cell-icon')?.textContent || '◆';
        inspector.querySelector('h3').textContent = `第 ${Number(cell.dataset.position) + 1} 格 · ${cell.dataset.label}`;
        inspector.querySelector('p').textContent = cell.dataset.description;
        inspector.querySelector('.inspector-status').textContent = landmark
            ? (cell.dataset.unlocked === '1' ? `已解锁 · 累计到访 ${cell.dataset.visits} 次` : '尚未解锁 · 首次到达可获得地标印章')
            : '落地后系统会自动处理并显示结果';
        cell.classList.add('is-inspected');
        cell.setAttribute('aria-expanded', 'true');
        inspector.hidden = false;
        windowRef.requestAnimationFrame(place);
    };

    cells.forEach(cell => {
        cell.addEventListener('click', () => show(cell));
        cell.addEventListener('mouseenter', () => {
            if (hoverQuery.matches) show(cell);
        });
        cell.addEventListener('mouseleave', () => {
            if (hoverQuery.matches) scheduleHide();
        });
        cell.addEventListener('focus', () => show(cell));
        cell.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                show(cell);
            }
        });
    });
    inspector.addEventListener('mouseenter', clearClose);
    inspector.addEventListener('mouseleave', scheduleHide);
    closeButton?.addEventListener('click', hide);
    documentRef.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !inspector.hidden) hide();
    });
    windowRef.addEventListener('resize', place);
    windowRef.addEventListener('scroll', place, true);

    return true;
}
