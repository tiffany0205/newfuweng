# 正方形棋盘与悬浮骰子 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将 36 格棋盘改成紧凑的正方形大富翁布局，并让中央悬浮 3D 骰子直接承担走棋操作。

**Architecture:** Blade 将格子坐标从 11×9 外围重排为 10×10 外围，中央区域改为无卡片的状态、骰子、结果和事件四层结构。现有走棋 API、幂等逻辑和 3D 骰面保持不变，JavaScript 继续通过 `#moveButton` 驱动请求，只把按钮容器改成骰子本体。

**Tech Stack:** Laravel Blade、CSS Grid、CSS 3D Transform、原生 JavaScript、PHPUnit、Vite。

## Global Constraints

- 36 个格子必须全部保留且顺序不变。
- 棋盘使用 10×10 网格，外围格数量严格为 36。
- 骰子本体是唯一主操作入口，不保留独立金色操作按钮。
- 服务端继续决定骰子点数、落点和奖励。
- 适配 1440×1000 和 390×844 视口，无页面级横向溢出。
- 支持 `prefers-reduced-motion: reduce`。

---

### Task 1: 锁定新页面结构

**Files:**
- Modify: `tests/Feature/ActivityFlowTest.php`
- Test: `tests/Feature/ActivityFlowTest.php`

- [x] **Step 1: Add a failing rendering contract**

断言活动页包含 `board-square`、`dice-trigger`、`center-statusline` 和 `event-rail`，并且不再包含 `class="command-card"`。

- [x] **Step 2: Run the focused test**

Run: `php vendor/bin/phpunit tests/Feature/ActivityFlowTest.php --filter square_board`

Expected: FAIL because the current board is rectangular and still renders the command card.

---

### Task 2: Re-map the 36-cell route

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`

- [x] **Step 1: Replace coordinate mapping**

Positions `0..9` use the top row, `10..18` the right column, `19..27` the bottom row in reverse, and `28..35` the left column in reverse.

- [x] **Step 2: Make the board square**

Use `grid-template: repeat(10,1fr)/repeat(10,1fr)` and `aspect-ratio:1`. Cap the desktop board width so it remains usable beside the 360px side panel; use full available width on mobile.

---

### Task 3: Replace the command card with a floating dice trigger

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`

- [x] **Step 1: Build the center hierarchy**

Render a compact status line at the top, a large transparent `#moveButton.dice-trigger` in the center, the final point result immediately beneath it, and an `#event.event-rail` at the bottom.

- [x] **Step 2: Scale the 3D object**

Increase the desktop cube to roughly 76px and mobile cube to roughly 54px. Add a soft floor shadow, hover tilt, pressed scale, and rolling state without adding a containing card.

- [x] **Step 3: Preserve interaction behavior**

Keep the existing request URL, frozen-state handling, minimum roll duration, real result display, destination aura, prize modal and error recovery.

---

### Task 4: Verify and deliver

**Files:**
- Modify: `docs/features-manual.md`
- Modify: `docs/operation-manual.md`

- [x] **Step 1: Update user documentation**

Describe the square board and direct dice interaction.

- [x] **Step 2: Run verification**

Run formatter, full PHPUnit suite, Vite production build, Composer audit and `git diff --check`.

- [x] **Step 3: Verify responsive rendering**

Inspect desktop and mobile screenshots, click the floating dice, verify the displayed point and confirm document width does not exceed viewport width.

- [x] **Step 4: Commit and push main**

Commit with `feat: redesign square board interaction` and push `origin main`.
