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
        prize:  { emoji: '💰', title: '🎊 恭喜中奖!',    detail: '奖品已记录，请联系客服领取' },
        battery:{ emoji: '🔋', title: '⚡ 获得电池!',    detail: '电池 +1，已自动发放到账户' },
        vip:    { emoji: '👑', title: '⭐ VIP 升级!',     detail: 'VIP 等级 +1，恭喜晋升!' },
    };
    const p = prizes[cellType] || { emoji: '🎉', title: '🎊 恭喜!', detail: '' };

    emoji.textContent = p.emoji;
    title.textContent = p.title;
    name.textContent = cellLabel;
    detail.textContent = p.detail;

    // Confetti
    const colors = ['#e2a83b','#f0c060','#d4442a','#4ade80','#38bdf8','#c4b5fd','#f87171'];
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
        if (data.dice_value) diceEl.textContent = faces[data.dice_value - 1];

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
        if (cell) cell.insertAdjacentHTML('beforeend', '<em class="piece">🚗</em>');

        // Prize detection
        const prizeTypes = ['prize', 'battery', 'vip'];
        if (prizeTypes.includes(data.cell_type)) {
            setTimeout(() => showPrizeModal(data.cell_type, data.result_text), 600);
        } else {
            setTimeout(() => location.reload(), 2200);
        }
    } catch (error) {
        clearInterval(rollInterval);
        eventEl.textContent = error.message;
        moveButton.disabled = false;
    }
});
