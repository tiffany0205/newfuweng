import test from 'node:test';
import assert from 'node:assert/strict';

import { feedbackPresentation, playFeedbackSound } from '../../resources/js/game-feedback.js';

const base = {
    cell_type: 'normal',
    final_cell_label: '测试格子',
    result_text: '测试结果',
    landmark_unlocked: false,
};

test('every successful move type has complete presentation metadata', () => {
    for (const feedbackType of ['normal', 'boost', 'landmark', 'reward', 'risk']) {
        const result = feedbackPresentation({ ...base, feedback_type: feedbackType });

        assert.ok(result.emoji, `${feedbackType} emoji`);
        assert.ok(result.title, `${feedbackType} title`);
        assert.ok(result.detail, `${feedbackType} detail`);
        assert.equal(result.tone, feedbackType);
    }
});

test('only rewards and newly unlocked landmarks celebrate', () => {
    assert.equal(feedbackPresentation({ ...base, feedback_type: 'normal' }).celebrate, false);
    assert.equal(feedbackPresentation({ ...base, feedback_type: 'risk' }).celebrate, false);
    assert.equal(feedbackPresentation({ ...base, feedback_type: 'landmark' }).celebrate, false);
    assert.equal(feedbackPresentation({ ...base, feedback_type: 'landmark', landmark_unlocked: true }).celebrate, true);
    assert.equal(feedbackPresentation({ ...base, feedback_type: 'reward' }).celebrate, true);
});

test('landmark feedback shows actual position progress and lucky points', () => {
    const result = feedbackPresentation({
        ...base,
        feedback_type: 'landmark',
        display_position: 17,
        landmark_count: 1,
        landmark_total: 12,
        lucky_points: 2,
        landmark_unlocked: true,
    });

    assert.match(result.detail, /第 17 格 · 测试格子/);
    assert.match(result.detail, /地标 1 \/ 12/);
    assert.match(result.detail, /幸运值 2/);
});

test('sound feedback silently handles mute and unsupported audio', () => {
    assert.doesNotThrow(() => playFeedbackSound('normal', 'normal', true));
    assert.doesNotThrow(() => playFeedbackSound('reward', 'vip', false, {}));
    assert.equal(playFeedbackSound('normal', 'normal', true), false);
    assert.equal(playFeedbackSound('reward', 'vip', false, {}), false);
});

test('structured settlement separates the main result, destination, changes, and balance', () => {
    const result = feedbackPresentation({
        ...base,
        feedback_type: 'landmark',
        result_text: '后退2格，后退 2 格，最终抵达 梦想港湾，重复印章转化为幸运值 +1，获得 2 点幸运值',
        result_summary: {
            headline: '后退 2 格',
            destination: { position: 29, label: '梦想港湾' },
            items: [
                { kind: 'landmark_repeat', label: '重复到达地标', value: '幸运值 +1' },
                { kind: 'lucky', label: '梦想港湾效果', value: '幸运值 +2' },
            ],
            balances: { lucky_points: 8 },
        },
    });

    assert.equal(result.title, '后退 2 格');
    assert.deepEqual(result.destination, { position: 29, label: '梦想港湾' });
    assert.deepEqual(result.items.map(item => item.value), ['幸运值 +1', '幸运值 +2']);
    assert.deepEqual(result.balances, [{ label: '当前幸运值', value: '8' }]);
    assert.equal(result.resultText, undefined);
});
