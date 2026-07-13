# 3D 骰子与中央操作区优化 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 为棋盘中央加入清晰的 3D 骰子翻滚反馈、最终点数提示、更醒目的当前位置呼吸光圈，并重做“掷出好运”操作区。

**Architecture:** 保持服务端骰子结果和现有走棋接口不变，只调整 Blade 语义结构、CSS 视觉状态和原生 JavaScript 状态切换。JavaScript 在请求发起时进入 `is-rolling` 状态，在响应后写入真实点数并进入 `is-result` 状态；CSS 负责 3D 骰子、按钮反馈和呼吸光圈，减少动态效果时立即显示静态结果。

**Tech Stack:** Laravel Blade、原生 JavaScript、CSS 3D Transform、PHPUnit、Vite。

## Global Constraints

- 点数仍由 PHP 服务端生成，前端不得指定或修改结果。
- 主要反馈动画控制在约 800ms，结果反馈在 300ms 内完成。
- PC 和 390px 手机视口均不得出现页面级横向滚动。
- 必须支持 `prefers-reduced-motion: reduce`。
- 不新增第三方动画依赖。

---

### Task 1: 锁定骰子舞台和结果反馈契约

**Files:**
- Modify: `tests/Feature/ActivityFlowTest.php`
- Test: `tests/Feature/ActivityFlowTest.php`

**Interfaces:**
- Consumes: authenticated `GET /activity`
- Produces: 页面包含 `dice-stage`、`dice-cube`、`roll-result`、`rollResultValue` 和 `current-position-aura`

- [x] **Step 1: Write the failing test**

```php
public function test_activity_renders_premium_dice_stage_and_result_feedback(): void
{
    $user = User::where('email', 'demo@example.com')->firstOrFail();

    $this->actingAs($user)->get('/activity')
        ->assertOk()
        ->assertSee('dice-stage', false)
        ->assertSee('dice-cube', false)
        ->assertSee('rollResultValue', false)
        ->assertSee('current-position-aura', false);
}
```

- [x] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/ActivityFlowTest.php --filter premium_dice`

Expected: FAIL because the new dice stage markup does not exist.

- [x] **Step 3: Leave implementation to Task 2**

The failing test defines the exact DOM hooks used by CSS and JavaScript.

---

### Task 2: Build the 3D dice stage and refined command panel

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/ActivityFlowTest.php`

**Interfaces:**
- Consumes: `POST /activity/move` response fields `dice_value`, `result_text`, `to_position`, `to_lap`, and `cell_type`
- Produces: `setDiceFace(value)`, `.is-rolling`, `.is-result`, and accessible live result text

- [x] **Step 1: Add semantic markup**

Replace the single glyph dice with a six-face cube inside `.dice-stage`. Add `.roll-result` with `aria-live="polite"`, showing “本次掷出” and `<b id="rollResultValue">—</b><span>点</span>`. Keep the action button ID and URL unchanged.

- [x] **Step 2: Add bounded 3D animation and control panel styling**

Create a 52px cube with six positioned faces, an 800ms rolling keyframe, a short result scale transition, and a flatter command surface with separate status, dice, result, and action rows. Use transform and opacity only for the main animation.

- [x] **Step 3: Connect request lifecycle to visual states**

On click, clear the prior result and add `.is-rolling`. On response, remove `.is-rolling`, rotate the cube to the returned face, write the true value into `#rollResultValue`, and add `.is-result`. On error, remove rolling state and restore the action.

- [x] **Step 4: Run the focused test**

Run: `php vendor/bin/phpunit tests/Feature/ActivityFlowTest.php --filter premium_dice`

Expected: PASS.

---

### Task 3: Strengthen current-position feedback and accessibility

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`

**Interfaces:**
- Consumes: `.cell.active` and the destination cell selected after a successful move
- Produces: `.current-position-aura` and `.just-arrived` feedback states

- [x] **Step 1: Add position aura markup**

Render `<span class="current-position-aura" aria-hidden="true"></span>` inside the active cell and insert the same element when JavaScript moves the piece.

- [x] **Step 2: Add dual-layer pulse**

Use pseudo-elements or box shadows to create two pulses extending roughly 1.8 times beyond the cell while keeping neighboring cell labels readable. Add a one-shot `.just-arrived` emphasis after movement.

- [x] **Step 3: Add reduced-motion fallback**

Disable cube rolling, result pulse, arrival emphasis, and repeating aura animation under `prefers-reduced-motion: reduce`; keep a static gold outline and the final numeric result visible.

---

### Task 4: Verify, document, and deliver

**Files:**
- Modify: `docs/features-manual.md`
- Modify: `docs/operation-manual.md`

**Interfaces:**
- Consumes: completed UI behavior
- Produces: user-facing description of dice feedback and reduced-motion behavior

- [x] **Step 1: Update documentation**

Document the 3D roll sequence, final point display, enlarged current-position pulse, and reduced-motion fallback.

- [x] **Step 2: Run all verification commands**

```bash
vendor/bin/pint --test
php vendor/bin/phpunit
npm run build
git diff --check
```

Expected: formatter passes, all tests pass, Vite build exits 0, and diff check prints nothing.

- [x] **Step 3: Verify responsive rendering**

Render `/activity` at 1440×1000 and 390×844, confirm the control panel is readable, the cube is centered, the active aura is visible, and no page-level horizontal overflow appears.

- [x] **Step 4: Commit and push**

```bash
git add docs resources tests
git commit -m "feat: enhance dice roll feedback"
git push origin main
```
