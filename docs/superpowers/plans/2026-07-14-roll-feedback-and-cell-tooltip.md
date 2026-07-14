# Roll Feedback and Cell Tooltip Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace dense roll-result prose with persisted, structured settlement summaries and make board-cell explanations prominent beside the inspected cell on PC and as a bottom sheet on mobile.

**Architecture:** Laravel records a JSON `result_summary` with each board move so idempotent replays return the original headline, destination, settlement items, and balances. A pure JavaScript presenter renders those fields without parsing prose, while a separate cell-inspector positioning module anchors one semantic inspector to the active board cell and switches presentation through CSS media queries.

**Tech Stack:** PHP 8.2/Laravel 12, SQLite-compatible migrations, Blade, vanilla JavaScript, CSS, PHPUnit, Node test runner, Vite.

## Global Constraints

- Do not change dice probability, cell effects, landmark values, or lucky-point calculations.
- Keep historical `result_text`; do not rewrite old board moves.
- Persist new summaries for idempotent replay and provide a safe summary fallback for historical rows.
- Do not add a third-party tooltip or animation dependency.
- Keep PC motion within 150–200ms and remove translation/scaling under `prefers-reduced-motion`.

---

### Task 1: Persist Structured Move Summaries

**Files:**
- Create: `database/migrations/2026_07_14_000600_add_result_summary_to_board_moves.php`
- Modify: `app/Http/Controllers/GameController.php`
- Modify: `tests/Feature/LandmarkHelpTest.php`
- Modify: `tests/Feature/ActivityFlowTest.php`

**Interfaces:**
- Produces response field `result_summary: {headline:string,destination:{position:int,label:string},items:array,balances:object}`.
- Each item is `{kind:string,label:string,value:string}`.

- [ ] Write failing tests that force a backward move into a repeated lucky landmark and assert separate “重复到达地标 / 幸运值 +1” and landmark-effect items, final destination, current lucky balance, persistence, and identical idempotent replay.
- [ ] Run focused PHPUnit tests and confirm failure because `result_summary` is absent.
- [ ] Add a nullable JSON/text-compatible column. Refactor landmark settlement to return structured items and lucky delta while retaining existing text. Build summaries before saving each move, persist JSON, and decode it in `moveResponse`; historical rows receive a basic fallback derived only from stable columns.
- [ ] Run focused and migration tests until green; run Pint; commit as `feat: structure roll settlement results`.

### Task 2: Render Result Hierarchy

**Files:**
- Modify: `resources/js/game-feedback.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/js/game-feedback.test.js`

**Interfaces:**
- Consumes `result_summary` from Task 1.
- Produces a modal with `[data-feedback-destination]`, `[data-feedback-items]`, and `[data-feedback-balances]`.

- [ ] Write failing Node tests asserting that the presenter uses the structured headline/destination/items/balances, distinguishes `+2` from current total, and does not expose the dense `result_text` as a primary display field.
- [ ] Verify RED, then implement a normalized presentation object and safe DOM list rendering with `textContent`.
- [ ] Replace the repeated prose block with final-position, settlement-list, and balance sections; retain concise fallbacks for old moves.
- [ ] Run Node tests, focused Blade/PHP tests, Vite, and interface detector; commit as `feat: clarify roll result hierarchy`.

### Task 3: Anchor the Board Cell Inspector

**Files:**
- Create: `resources/js/cell-inspector.js`
- Create: `tests/js/cell-inspector.test.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/LandmarkHelpTest.php`

**Interfaces:**
- Produces pure `positionCellInspector(anchorRect, panelRect, viewport)` and `initCellInspector(document, window)` functions.
- Consumes cell `data-position`, category, label, description, unlocked, and visits fields.

- [ ] Write failing Node tests for placement above, below, left/right viewport clamping, and missing inspector initialization; write a failing render test for semantic title linkage and position hooks.
- [ ] Verify RED, implement fixed positioning and pointer direction, and move existing inspector listeners into the focused module.
- [ ] Add hover/focus active state, 150ms delayed leave, tooltip hover retention, resize/scroll repositioning, Escape close, `aria-expanded`, and a mobile bottom-sheet CSS mode.
- [ ] Run focused Node/PHP tests, build, and detector; commit as `feat: anchor board cell explanations`.

### Task 4: Documentation and Delivery

**Files:**
- Modify: `docs/features-manual.md`
- Modify: `docs/ai-usage-log.md`

- [ ] Document the four result layers, increment-versus-balance wording, PC hover/keyboard behavior, and mobile tap behavior.
- [ ] Run Pint, full PHPUnit, full Node tests, Vite build, Composer audit, interface detector, `git diff --check`, and inspect the complete diff against the approved spec.
- [ ] Record actual red/green and full-verification evidence, commit as `docs: explain clearer board feedback`, push `main`, and confirm local/remote hashes match.
