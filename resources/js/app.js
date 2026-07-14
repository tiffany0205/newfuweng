import './bootstrap';
import { feedbackPresentation, playFeedbackSound } from './game-feedback';

function uuidv4(){return'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const r=crypto.getRandomValues(new Uint8Array(1))[0]%16|0;return(c==='x'?r:r&0x3|0x8).toString(16)})}

function addRecordCell(row, value, className = '') {
    const cell = document.createElement('td');
    cell.textContent = value ?? '—';
    if (className) cell.className = className;
    row.appendChild(cell);
}

function prependChanceRecord(record) {
    if (!record) return;
    const tbody = document.querySelector('.records details:first-of-type tbody');
    const id = String(record.id);
    if (!tbody || tbody.querySelector(`[data-record-id="${id}"]`)) return;

    tbody.querySelector('tr:not([data-record-id])')?.remove();
    const row = document.createElement('tr');
    const amount = Number(record.amount);
    row.className = 'chance-record-row';
    row.dataset.recordId = id;
    addRecordCell(row, record.created_at);
    addRecordCell(row, record.remark);
    addRecordCell(row, `${amount > 0 ? '+' : ''}${amount}`, amount > 0 ? 'plus' : 'minus');
    addRecordCell(row, record.balance_after);
    tbody.prepend(row);
}

// Copy to clipboard (with HTTP fallback)
function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    // Fallback for HTTP pages
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    ta.style.top = '-9999px';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(ta);
}

document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', async () => {
        await copyText(button.dataset.copy);
        const old = button.textContent;
        button.textContent = '已复制!';
        button.style.background = '#4ade80';
        button.style.color = '#000';
        setTimeout(() => {
            button.textContent = old;
            button.style.background = '';
            button.style.color = '';
        }, 1500);
    });
});

// Independently cursor-paginate the two activity record tables.
document.querySelectorAll('.record-loader').forEach(loader => {
    const details = loader.closest('details');
    const tbody = details?.querySelector('tbody');
    const button = loader.querySelector('button');
    if (!tbody || !button) return;

    const seen = new Set([...tbody.querySelectorAll('[data-record-id]')].map(row => row.dataset.recordId));
    let cursor = loader.dataset.cursor || null;
    let hasMore = loader.dataset.hasMore === '1';
    let loading = false;
    let observer = null;

    function addCell(row, value, className = '') {
        const cell = document.createElement('td');
        cell.textContent = value ?? '—';
        if (className) cell.className = className;
        row.appendChild(cell);
    }

    function appendRecord(record) {
        const id = String(record.id);
        if (seen.has(id) || tbody.querySelector(`[data-record-id="${id}"]`)) return;

        const row = document.createElement('tr');
        row.dataset.recordId = id;
        if (loader.dataset.recordType === 'chance') {
            row.className = 'chance-record-row';
            addCell(row, record.created_at);
            addCell(row, record.remark);
            const amount = Number(record.amount);
            addCell(row, `${amount > 0 ? '+' : ''}${amount}`, amount > 0 ? 'plus' : 'minus');
            addCell(row, record.balance_after);
        } else {
            row.className = 'winning-record-row';
            addCell(row, record.created_at);
            addCell(row, record.prize_name);
            addCell(row, record.status_label);
        }
        tbody.appendChild(row);
        seen.add(id);
    }

    function setComplete() {
        hasMore = false;
        loader.dataset.hasMore = '0';
        button.textContent = '已加载全部';
        button.disabled = true;
        observer?.disconnect();
    }

    async function loadNextPage() {
        if (loading || !hasMore || !cursor) return;
        loading = true;
        button.disabled = true;
        button.textContent = '加载中…';

        try {
            const url = new URL(loader.dataset.url, window.location.origin);
            url.searchParams.set('cursor', cursor);
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || '加载失败');

            payload.data.forEach(appendRecord);
            cursor = payload.next_cursor ? String(payload.next_cursor) : null;
            loader.dataset.cursor = cursor || '';
            hasMore = payload.has_more === true;
            loader.dataset.hasMore = hasMore ? '1' : '0';
            if (hasMore && cursor) {
                button.textContent = '加载更多';
                button.disabled = false;
            } else {
                setComplete();
            }
        } catch (_) {
            button.textContent = '加载失败，点击重试';
            button.disabled = false;
        } finally {
            loading = false;
        }
    }

    button.addEventListener('click', loadNextPage);
    if (!hasMore || !cursor) {
        setComplete();
    } else if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) loadNextPage();
        }, { rootMargin: '180px 0px' });
        observer.observe(loader);
    }
});

// Board legend and contextual cell explanation (hover on PC, tap on mobile).
const inspector = document.querySelector('#cellInspector');
const categoryNames = { safe: '安全格', landmark: '地标格', boost: '增益格', risk: '风险格', reward: '奖励格' };
function inspectCell(cell) {
    if (!inspector) return;
    const landmark = cell.dataset.category === 'landmark';
    inspector.querySelector('.inspector-type').textContent = categoryNames[cell.dataset.category] || '棋盘格';
    inspector.querySelector('.inspector-icon').textContent = cell.querySelector('.cell-icon')?.textContent || '◆';
    inspector.querySelector('h3').textContent = cell.dataset.label;
    inspector.querySelector('p').textContent = cell.dataset.description;
    inspector.querySelector('.inspector-status').textContent = landmark
        ? (cell.dataset.unlocked === '1' ? `已解锁 · 累计到访 ${cell.dataset.visits} 次` : '尚未解锁 · 首次到达可获得地标印章')
        : '落地后系统会自动处理并显示结果';
    inspector.hidden = false;
}
document.querySelectorAll('.cell').forEach(cell => {
    cell.addEventListener('click', () => inspectCell(cell));
    cell.addEventListener('mouseenter', () => { if (window.matchMedia('(hover:hover)').matches) inspectCell(cell); });
    cell.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            inspectCell(cell);
        }
    });
});
inspector?.querySelector('.inspector-close')?.addEventListener('click', () => inspector.hidden = true);
const legend = document.querySelector('#boardLegend');
document.querySelector('[data-open-legend]')?.addEventListener('click', () => {
    legend.hidden = false;
    legend.querySelector('button')?.focus();
});
legend?.querySelector('button')?.addEventListener('click', () => legend.hidden = true);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        if (inspector) inspector.hidden = true;
        if (legend) legend.hidden = true;
    }
});

// Five-step first visit tutorial. Completion is persisted server-side.
const tutorial = document.querySelector('#tutorialOverlay');
if (tutorial) {
    const slides = [
        ['🎲', '欢迎来到幸运跳棋', '完成签到、任务、充值和邀请，获得跳棋机会。'],
        ['🗺️', '掷骰前进', '每次消耗 1 次机会，服务端随机生成点数并触发落脚格效果。'],
        ['📍', '收集地标印章', '紫色地标首次到达解锁印章，重复到达会转化为幸运值。'],
        ['🎁', '开启成长宝箱', '完成任务、圈数和地标收集，在幸运中心领取阶段奖励。'],
        ['🏆', '向排行榜冲刺', '排名比较圈数、当前格子和到达时间，现在开始你的旅程吧。'],
    ];
    let slide = 0;
    const next = tutorial.querySelector('.tutorial-next');
    next.addEventListener('click', async () => {
        slide++;
        if (slide >= slides.length) {
            const form = tutorial.querySelector('form');
            await fetch(form.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } });
            tutorial.remove();
            return;
        }
        const [icon, title, text] = slides[slide];
        tutorial.querySelector('.tutorial-icon').textContent = icon;
        tutorial.querySelector('h2').textContent = title;
        tutorial.querySelector('p').textContent = text;
        tutorial.querySelector('.tutorial-step').textContent = `0${slide + 1} / 05`;
        tutorial.querySelectorAll('.tutorial-dots i').forEach((dot, index) => dot.classList.toggle('active', index === slide));
        if (slide === slides.length - 1) next.textContent = '开始冒险';
    });
}

const soundStorageKey = 'gameSoundMuted';

function isSoundMuted() {
    try { return localStorage.getItem(soundStorageKey) === '1'; } catch (_) { return false; }
}

function setSoundMuted(muted) {
    try { localStorage.setItem(soundStorageKey, muted ? '1' : '0'); } catch (_) {}
}

const soundToggle = document.querySelector('#soundToggle');
function renderSoundToggle() {
    if (!soundToggle) return;
    const muted = isSoundMuted();
    soundToggle.setAttribute('aria-pressed', muted ? 'true' : 'false');
    soundToggle.setAttribute('aria-label', muted ? '开启掷骰音效' : '关闭掷骰音效');
    soundToggle.querySelector('span').textContent = muted ? '🔇' : '🔊';
    soundToggle.querySelector('small').textContent = muted ? '音效关' : '音效开';
}
soundToggle?.addEventListener('click', () => {
    setSoundMuted(!isSoundMuted());
    renderSoundToggle();
});
renderSoundToggle();

// Unified feedback for every successful move
function showRollFeedback(data) {
    const template = document.querySelector('#rollFeedbackModal');
    if (!template) return;
    document.querySelector('#rollFeedbackOverlay')?.remove();
    const clone = template.content.cloneNode(true);
    document.body.appendChild(clone);

    const presentation = feedbackPresentation(data);
    const emoji = document.querySelector('#prizeEmoji');
    const kicker = document.querySelector('#feedbackKicker');
    const title = document.querySelector('#prizeTitle');
    const name = document.querySelector('#prizeName');
    const detail = document.querySelector('#prizeDetail');
    const result = document.querySelector('#feedbackResult');
    const confetti = document.querySelector('#confetti');
    const overlay = document.querySelector('#rollFeedbackOverlay');
    const closeBtn = document.querySelector('#prizeClose');

    overlay.classList.add(`feedback-${data.feedback_type || 'normal'}`);
    if (presentation.celebrate) overlay.classList.add('is-celebration');
    emoji.textContent = presentation.emoji;
    kicker.textContent = presentation.kicker;
    title.textContent = presentation.title;
    name.textContent = data.dice_value ? `掷出 ${data.dice_value} 点 · ${data.final_cell_label}` : data.final_cell_label;
    detail.textContent = presentation.detail;
    result.textContent = data.result_text;
    closeBtn.textContent = presentation.celebrate ? '开心收下' : '继续前进';

    if (presentation.celebrate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const colors = ['#f2d692','#d6b36a','#6c8cff','#63d9e6','#65d49c','#ffffff'];
        let html = '';
        for (let i = 0; i < 42; i++) {
            const x = Math.random() * 100;
            const delay = Math.random() * .7;
            const size = 5 + Math.random() * 7;
            const color = colors[Math.floor(Math.random() * colors.length)];
            html += `<span class="confetti-piece" style="left:${x}%;width:${size}px;height:${size*1.5}px;background:${color};animation-delay:${delay}s;animation-duration:${1.6+Math.random()}s"></span>`;
        }
        confetti.innerHTML = html;
    }

    let closed = false;
    function handleKeydown(event) {
        if (event.key === 'Escape') close();
    }
    function close() {
        if (closed) return;
        closed = true;
        document.removeEventListener('keydown', handleKeydown);
        overlay.classList.add('is-closing');
        setTimeout(() => location.reload(), 160);
    }
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', handleKeydown);
    closeBtn.focus();
    if (presentation.autoCloseMs) setTimeout(close, presentation.autoCloseMs);
}

// Dice / move button
const moveButton = document.querySelector('#moveButton');
if (moveButton) moveButton.addEventListener('click', async () => {
    if (moveButton.disabled) return;
    moveButton.disabled = true;

    const eventEl = document.querySelector('#event');
    const diceConsole = document.querySelector('#diceConsole');
    const diceCube = document.querySelector('#diceCube');
    const rollResult = document.querySelector('#rollResult');
    const resultValue = document.querySelector('#rollResultValue');
    const resultUnit = document.querySelector('#rollResultUnit');
    const resultLabel = rollResult?.querySelector(':scope > span');
    const shouldRoll = moveButton.dataset.frozen !== '1';
    const rollStartedAt = performance.now();
    const faceClasses = ['face-1', 'face-2', 'face-3', 'face-4', 'face-5', 'face-6'];

    eventEl.textContent = shouldRoll ? '骰子翻滚中，好运正在生成…' : '正在解除冰冻状态…';
    rollResult?.classList.remove('is-result');
    if (resultLabel) resultLabel.textContent = shouldRoll ? '骰子翻滚中' : '正在解冻';
    if (resultValue) resultValue.textContent = '—';
    if (resultUnit) resultUnit.textContent = shouldRoll ? '点' : '';
    if (shouldRoll) diceConsole?.classList.add('is-rolling');

    try {
        const response = await fetch(moveButton.dataset.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ request_id: uuidv4() })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || '操作失败');

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const remainingRollTime = shouldRoll && !reducedMotion ? Math.max(0, 820 - (performance.now() - rollStartedAt)) : 0;
        if (remainingRollTime) await new Promise(resolve => setTimeout(resolve, remainingRollTime));

        diceConsole?.classList.remove('is-rolling');
        if (data.dice_value && diceCube) {
            diceCube.classList.remove(...faceClasses);
            diceCube.classList.add(`face-${Math.min(6, data.dice_value)}`);
        }
        if (resultLabel) resultLabel.textContent = data.dice_value ? '本次掷出' : '解冻完成';
        if (resultValue) resultValue.textContent = data.dice_value || '✓';
        if (resultUnit) resultUnit.textContent = data.dice_value ? '点' : '';
        rollResult?.classList.add('is-result');

        eventEl.textContent = `第 ${data.display_position} 格 · ${data.result_text}`;

        // Update stats
        const remainingChance = Math.max(0, Number(document.querySelector('#chance').textContent) - 1);
        document.querySelector('#chance').textContent = remainingChance;
        const centerChance = document.querySelector('#centerChance');
        if (centerChance) centerChance.textContent = remainingChance;
        document.querySelector('#lap').textContent = data.to_lap;
        document.querySelector('#position').textContent = data.display_position;
        const centerPosition = document.querySelector('#centerPosition');
        if (centerPosition) centerPosition.textContent = data.display_position;
        prependChanceRecord(data.chance_transaction);

        // Update board
        document.querySelectorAll('.cell').forEach(c => {
            c.classList.remove('active');
            c.classList.remove('just-arrived');
            c.querySelector('.piece')?.remove();
            c.querySelector('.current-position-aura')?.remove();
        });
        const cell = document.querySelector(`[data-position="${data.to_position}"]`);
        cell?.classList.add('active');
        cell?.classList.add('just-arrived');
        if (cell) cell.insertAdjacentHTML('beforeend', `<span class="current-position-aura" aria-hidden="true"></span><em class="piece">${window.gameConfig.skin || '🚗'}</em>`);
        setTimeout(() => cell?.classList.remove('just-arrived'), 500);

        setTimeout(() => {
            playFeedbackSound(data.feedback_type, data.cell_type, isSoundMuted(), window);
            showRollFeedback(data);
        }, reducedMotion ? 80 : 420);
    } catch (error) {
        diceConsole?.classList.remove('is-rolling');
        if (resultLabel) resultLabel.textContent = '操作未完成';
        if (resultValue) resultValue.textContent = '—';
        if (resultUnit) resultUnit.textContent = '';
        eventEl.textContent = error.message;
        moveButton.disabled = false;
    }
});
