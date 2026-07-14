const tones = {
    normal: [392, 523.25],
    boost: [440, 587.33, 659.25],
    landmark: [523.25, 659.25, 783.99],
    reward: [523.25, 659.25, 783.99, 1046.5],
    risk: [311.13, 233.08],
};

const presentations = {
    normal: { emoji: '🎲', kicker: '本次结果', title: '平稳抵达', detail: '棋子已到达新的位置' },
    boost: { emoji: '🚀', kicker: '前进加速', title: '好运助力', detail: '增益效果已经完成结算' },
    landmark: { emoji: '📍', kicker: '地标旅程', title: '抵达地标', detail: '本次地标访问已记录到图鉴' },
    reward: { emoji: '🎁', kicker: '幸运奖励', title: '奖励已获得', detail: '奖励结果已经完成记录' },
    risk: { emoji: '⚠️', kicker: '冒险事件', title: '遭遇挑战', detail: '风险效果已经完成结算' },
};

export function feedbackPresentation(data) {
    const kind = Object.hasOwn(presentations, data.feedback_type) ? data.feedback_type : 'normal';
    const result = { ...presentations[kind] };
    const destination = data.display_position
        ? `第 ${data.display_position} 格 · ${data.final_cell_label || '当前位置'}`
        : '';

    if (kind === 'landmark') {
        result.title = data.landmark_unlocked ? '新地标已解锁' : '再次抵达地标';
        result.detail = [
            destination,
            `地标 ${data.landmark_count ?? 0} / ${data.landmark_total ?? 0}`,
            `幸运值 ${data.lucky_points ?? 0}`,
        ].filter(Boolean).join(' · ');
    } else if (kind === 'reward') {
        const reward = {
            vip: ['👑', 'VIP 等级提升', '尊享奖励已实时到账'],
            battery: ['🔋', '能量补给到账', '电池奖励已实时到账'],
            prize: ['💎', '幸运大奖已锁定', '请在中奖列表查看发放进度'],
            chance: ['🎲', '额外机会到账', '跳棋机会已经更新'],
        }[data.cell_type];
        if (reward) [result.emoji, result.title, result.detail] = reward;
    } else if (kind === 'risk') {
        const risk = {
            freeze: ['🧊', '棋子被冰冻'],
            bomb: ['💣', '触发炸弹事件'],
            backward: ['↩️', '棋子向后移动'],
        }[data.cell_type];
        if (risk) [result.emoji, result.title] = risk;
    }
    if (kind !== 'landmark' && destination) {
        result.detail = `${destination} · ${result.detail}`;
    }

    const summary = data.result_summary;
    if (summary) {
        result.title = summary.headline || result.title;
        result.detail = '';
        result.destination = summary.destination || null;
        result.items = Array.isArray(summary.items) ? summary.items : [];
        result.balances = [
            summary.balances?.lucky_points !== undefined
                ? { label: '当前幸运值', value: String(summary.balances.lucky_points) }
                : null,
        ].filter(Boolean);
    } else {
        result.destination = data.display_position ? { position: data.display_position, label: data.final_cell_label || '当前位置' } : null;
        result.items = [];
        result.balances = [];
    }

    return {
        ...result,
        tone: kind,
        celebrate: kind === 'reward' || (kind === 'landmark' && Boolean(data.landmark_unlocked)),
        autoCloseMs: ['normal', 'boost'].includes(kind) ? 2600 : 0,
    };
}

export function playFeedbackSound(kind, cellType, muted, scope = globalThis) {
    if (muted) return false;

    const AudioContext = scope?.AudioContext || scope?.webkitAudioContext || scope?.window?.AudioContext || scope?.window?.webkitAudioContext;
    if (typeof AudioContext !== 'function') return false;

    try {
        const audio = new AudioContext();
        if (audio.state === 'suspended' && typeof audio.resume === 'function') audio.resume();
        const sequence = tones[kind] || tones.normal;
        const extraVipNote = kind === 'reward' && cellType === 'vip' ? [1318.51] : [];
        [...sequence, ...extraVipNote].forEach((frequency, index) => {
            const oscillator = audio.createOscillator();
            const gain = audio.createGain();
            const start = audio.currentTime + index * 0.085;
            oscillator.type = kind === 'risk' ? 'triangle' : 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.linearRampToValueAtTime(kind === 'risk' ? 0.045 : 0.06, start + 0.018);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.28);
            oscillator.connect(gain).connect(audio.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.3);
        });
        const duration = ([...sequence, ...extraVipNote].length * 85) + 340;
        setTimeout(() => {
            try {
                const closed = audio.close();
                if (closed?.catch) closed.catch(() => {});
            } catch (_) {}
        }, duration);

        return true;
    } catch (_) {
        return false;
    }
}
