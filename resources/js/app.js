import './bootstrap';

function uuidv4(){return'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const r=crypto.getRandomValues(new Uint8Array(1))[0]%16|0;return(c==='x'?r:r&0x3|0x8).toString(16)})}

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

// Prize modal
function showPrizeModal(cellType, cellLabel) {
    const template = document.querySelector('#prizeModal');
    if (!template) return;
    const clone = template.content.cloneNode(true);
    document.body.appendChild(clone);

    const emoji = document.querySelector('#prizeEmoji');
    const title = document.querySelector('#prizeTitle');
    const name = document.querySelector('#prizeName');
    const detail = document.querySelector('#prizeDetail');
    const confetti = document.querySelector('#confetti');
    const overlay = document.querySelector('#prizeOverlay');
    const closeBtn = document.querySelector('#prizeClose');

    const prizes = {
        prize:  { emoji: '💎', title: '恭喜抽中大奖', detail: '幸运奖励已锁定，可在中奖列表查看发放进度' },
        battery:{ emoji: '🔋', title: '能量补给到账', detail: '电池奖励已实时发放至你的账户' },
        vip:    { emoji: '👑', title: '尊享等级提升', detail: 'VIP 等级已提升，新的尊享权益已生效' },
        landmark: { emoji: '📍', title: '新地标已解锁', detail: '旅行印章已收录到地标图鉴，继续收集可开启阶段宝箱' },
    };
    const p = prizes[cellType] || { emoji: '🎉', title: '🎊 恭喜!', detail: '' };

    emoji.textContent = p.emoji;
    title.textContent = p.title;
    name.textContent = cellLabel;
    detail.textContent = p.detail;

    // A short, dependency-free celebration chime. Silently ignored when audio is blocked.
    try {
        const audio = new (window.AudioContext || window.webkitAudioContext)();
        [523.25, 659.25, 783.99, 1046.5].forEach((frequency, index) => {
            const oscillator = audio.createOscillator();
            const gain = audio.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0, audio.currentTime + index * .09);
            gain.gain.linearRampToValueAtTime(.07, audio.currentTime + index * .09 + .02);
            gain.gain.exponentialRampToValueAtTime(.001, audio.currentTime + index * .09 + .35);
            oscillator.connect(gain).connect(audio.destination);
            oscillator.start(audio.currentTime + index * .09);
            oscillator.stop(audio.currentTime + index * .09 + .38);
        });
    } catch (_) {}

    // Confetti
    const colors = ['#f2d692','#d6b36a','#6c8cff','#63d9e6','#65d49c','#ffffff'];
    let html = '';
    for (let i = 0; i < 60; i++) {
        const x = Math.random() * 100;
        const delay = Math.random() * 1.5;
        const size = 6 + Math.random() * 10;
        const color = colors[Math.floor(Math.random() * colors.length)];
        html += `<span class="confetti-piece" style="left:${x}%;width:${size}px;height:${size*1.6}px;background:${color};animation-delay:${delay}s;animation-duration:${2+Math.random()*2}s"></span>`;
    }
    confetti.innerHTML = html;

    function close() {
        overlay.remove();
        location.reload();
    }
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
}

// Dice / move button
const moveButton = document.querySelector('#moveButton');
if (moveButton) moveButton.addEventListener('click', async () => {
    if (moveButton.disabled) return;
    moveButton.disabled = true;

    const eventEl = document.querySelector('#event');
    const diceEl = document.querySelector('#dice');
    eventEl.textContent = '骰子滚动中…';
    diceEl.textContent = '🎲';

    const faces = ['⚀','⚁','⚂','⚃','⚄','⚅'];
    let rollCount = 0;
    const rollInterval = setInterval(() => {
        diceEl.textContent = faces[Math.floor(Math.random() * 6)];
        rollCount++;
    }, 80);

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

        clearInterval(rollInterval);
        if (data.dice_value) diceEl.textContent = data.dice_value <= 6 ? faces[data.dice_value - 1] : `×${data.dice_value}`;

        eventEl.textContent = data.result_text;

        // Update stats
        document.querySelector('#chance').textContent = Math.max(0, Number(document.querySelector('#chance').textContent) - 1);
        document.querySelector('#lap').textContent = data.to_lap;
        document.querySelector('#position').textContent = data.to_position;

        // Update board
        document.querySelectorAll('.cell').forEach(c => {
            c.classList.remove('active');
            c.querySelector('.piece')?.remove();
        });
        const cell = document.querySelector(`[data-position="${data.to_position}"]`);
        cell?.classList.add('active');
        if (cell) cell.insertAdjacentHTML('beforeend', `<em class="piece">${window.gameConfig.skin || '🚗'}</em>`);

        // Prize detection
        const prizeTypes = ['prize', 'battery', 'vip'];
        const unlockedLandmark = data.result_text.includes('新地标印章已解锁');
        if (prizeTypes.includes(data.cell_type) || unlockedLandmark) {
            setTimeout(() => showPrizeModal(unlockedLandmark ? 'landmark' : data.cell_type, data.result_text), 600);
        } else {
            setTimeout(() => location.reload(), 2200);
        }
    } catch (error) {
        clearInterval(rollInterval);
        eventEl.textContent = error.message;
        moveButton.disabled = false;
    }
});
