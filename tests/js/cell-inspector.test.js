import test from 'node:test';
import assert from 'node:assert/strict';

import { initCellInspector, positionCellInspector } from '../../resources/js/cell-inspector.js';

const panel = { width: 280, height: 180 };
const viewport = { width: 1200, height: 800, gap: 10, padding: 12 };

test('cell inspector is centered above the anchor when there is room', () => {
    assert.deepEqual(
        positionCellInspector({ left: 500, right: 560, top: 400, bottom: 460, width: 60, height: 60 }, panel, viewport),
        { left: 390, top: 210, placement: 'above', arrowOffset: 140 },
    );
});

test('cell inspector moves below the anchor near the top edge', () => {
    assert.deepEqual(
        positionCellInspector({ left: 500, right: 560, top: 40, bottom: 100, width: 60, height: 60 }, panel, viewport),
        { left: 390, top: 110, placement: 'below', arrowOffset: 140 },
    );
});

test('cell inspector clamps to both viewport edges and keeps its arrow aimed at the cell', () => {
    assert.deepEqual(
        positionCellInspector({ left: 2, right: 42, top: 400, bottom: 440, width: 40, height: 40 }, panel, viewport),
        { left: 12, top: 210, placement: 'above', arrowOffset: 10 },
    );
    assert.deepEqual(
        positionCellInspector({ left: 1170, right: 1210, top: 400, bottom: 440, width: 40, height: 40 }, panel, viewport),
        { left: 908, top: 210, placement: 'above', arrowOffset: 280 },
    );
});

test('cell inspector initialization is a no-op when markup is absent', () => {
    assert.equal(initCellInspector({ querySelector: () => null }), false);
});
