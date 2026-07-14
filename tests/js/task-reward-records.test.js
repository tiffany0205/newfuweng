import test from 'node:test';
import assert from 'node:assert/strict';

import {
    createTaskRecordRow,
    initTaskRewardRecords,
    taskRecordCopy,
    taskRecordPageState,
} from '../../resources/js/task-reward-records.js';

class FakeNode {
    constructor(tagName = '') {
        this.tagName = tagName;
        this.children = [];
        this.className = '';
        this.textContent = '';
        this.attributes = {};
    }

    append(...nodes) {
        this.children.push(...nodes);
    }

    setAttribute(name, value) {
        this.attributes[name] = value;
    }
}

const fakeDocument = {
    createElement: (tagName) => new FakeNode(tagName),
};

test('record copy clearly distinguishes invites and qualifying recharges', () => {
    assert.deepEqual(taskRecordCopy('invite'), {
        kicker: '邀请奖励',
        title: '邀请记录',
        description: '好友通过你的邀请加入后，5 次机会自动到账',
        event: '通过邀请成功加入',
        empty: '还没有邀请记录，复制邀请链接分享给好友吧',
    });
    assert.equal(taskRecordCopy('friend_recharge').title, '好友充值达标记录');
    assert.equal(taskRecordCopy('friend_recharge').event, '首次累计充值达到 10 USDT');
});

test('record rows render server values with textContent and accessible friend labels', () => {
    const row = createTaskRecordRow(fakeDocument, {
        friend_name: '<img src=x onerror=alert(1)>',
        occurred_at: '2026-07-14 12:30:00',
        chance_awarded: 10,
    }, 'friend_recharge');

    assert.equal(row.children[1].children[0].textContent, '<img src=x onerror=alert(1)>');
    assert.equal(row.children[1].children[1].textContent, '首次累计充值达到 10 USDT');
    assert.equal(row.children[2].textContent, '+10 次');
    assert.equal(row.attributes['aria-label'], '<img src=x onerror=alert(1)>，首次累计充值达到 10 USDT，获得 10 次机会，2026-07-14 12:30:00');
});

test('page state selects empty, complete, and load-more states', () => {
    assert.deepEqual(taskRecordPageState({ data: [], has_more: false, next_cursor: null }), { empty: true, hasMore: false, cursor: null });
    assert.deepEqual(taskRecordPageState({ data: [{}], has_more: false, next_cursor: null }), { empty: false, hasMore: false, cursor: null });
    assert.deepEqual(taskRecordPageState({ data: [{}], has_more: true, next_cursor: 42 }), { empty: false, hasMore: true, cursor: 42 });
});

test('initialization is a no-op when task record dialog is absent', () => {
    assert.equal(initTaskRewardRecords({ querySelector: () => null }), false);
});
