const COPY = {
    invite: {
        kicker: '邀请奖励',
        title: '邀请记录',
        description: '好友通过你的邀请加入后，5 次机会自动到账',
        event: '通过邀请成功加入',
        empty: '还没有邀请记录，复制邀请链接分享给好友吧',
    },
    friend_recharge: {
        kicker: '首充奖励',
        title: '好友充值达标记录',
        description: '每位好友首次累计充值满 10 USDT，仅奖励一次',
        event: '首次累计充值达到 10 USDT',
        empty: '还没有好友充值达标记录，邀请好友参与活动吧',
    },
};

export function taskRecordCopy(type) {
    return COPY[type] ?? COPY.invite;
}

export function taskRecordPageState(payload) {
    return {
        empty: payload.data.length === 0,
        hasMore: Boolean(payload.has_more),
        cursor: payload.has_more ? Number(payload.next_cursor) : null,
    };
}

export function createTaskRecordRow(documentRef, record, type) {
    const copy = taskRecordCopy(type);
    const row = documentRef.createElement('li');
    row.className = 'task-record-row';
    row.setAttribute('aria-label', `${record.friend_name}，${copy.event}，获得 ${record.chance_awarded} 次机会，${record.occurred_at}`);

    const avatar = documentRef.createElement('span');
    avatar.className = 'task-record-avatar';
    avatar.textContent = String(record.friend_name).slice(0, 1);
    avatar.setAttribute('aria-hidden', 'true');

    const detail = documentRef.createElement('span');
    detail.className = 'task-record-copy';
    const name = documentRef.createElement('b');
    name.textContent = record.friend_name;
    const event = documentRef.createElement('small');
    event.textContent = copy.event;
    const time = documentRef.createElement('time');
    time.textContent = record.occurred_at;
    detail.append(name, event, time);

    const chance = documentRef.createElement('strong');
    chance.className = 'task-record-chance';
    chance.textContent = `+${record.chance_awarded} 次`;
    row.append(avatar, detail, chance);

    return row;
}

export function initTaskRewardRecords(documentRef = document, fetchImpl = globalThis.fetch) {
    const dialog = documentRef.querySelector('#taskRewardDialog');
    if (!dialog) return false;

    const sheet = dialog.querySelector('.task-record-sheet');
    const list = dialog.querySelector('[data-task-record-list]');
    const status = dialog.querySelector('[data-task-record-status]');
    const more = dialog.querySelector('[data-task-record-more]');
    const retry = dialog.querySelector('[data-task-record-retry]');
    const title = dialog.querySelector('[data-task-record-title]');
    const kicker = dialog.querySelector('[data-task-record-kicker]');
    const description = dialog.querySelector('[data-task-record-description]');
    let type = 'invite';
    let cursor = null;
    let lastTrigger = null;
    let loading = false;

    const close = () => {
        if (dialog.hidden) return;
        dialog.hidden = true;
        documentRef.body?.classList.remove('task-record-open');
        lastTrigger?.focus();
    };

    const load = async (append = false) => {
        if (loading) return;
        loading = true;
        more.disabled = true;
        retry.hidden = true;
        status.textContent = append ? '正在加载更多记录…' : '正在加载记录…';
        if (!append) list.replaceChildren();

        try {
            const parameters = new URLSearchParams({ type });
            if (append && cursor) parameters.set('cursor', String(cursor));
            const response = await fetchImpl(`${dialog.dataset.url}?${parameters}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('记录加载失败');
            const payload = await response.json();
            payload.data.forEach(record => list.append(createTaskRecordRow(documentRef, record, type)));
            const page = taskRecordPageState(payload);
            cursor = page.cursor;
            status.textContent = list.children.length === 0 ? taskRecordCopy(type).empty : '';
            more.hidden = !page.hasMore;
            more.disabled = false;
        } catch {
            status.textContent = '记录暂时加载失败，请稍后重试';
            retry.hidden = false;
            more.hidden = true;
        } finally {
            loading = false;
        }
    };

    const open = (trigger) => {
        type = trigger.dataset.taskRecords;
        cursor = null;
        lastTrigger = trigger;
        const copy = taskRecordCopy(type);
        kicker.textContent = copy.kicker;
        title.textContent = copy.title;
        description.textContent = copy.description;
        dialog.hidden = false;
        documentRef.body?.classList.add('task-record-open');
        sheet.focus();
        load(false);
    };

    documentRef.querySelectorAll('[data-task-records]').forEach(trigger => trigger.addEventListener('click', () => open(trigger)));
    dialog.querySelectorAll('[data-task-record-close]').forEach(control => control.addEventListener('click', close));
    more.addEventListener('click', () => load(true));
    retry.addEventListener('click', () => load(list.children.length > 0));
    documentRef.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !dialog.hidden) close();
    });

    return true;
}
