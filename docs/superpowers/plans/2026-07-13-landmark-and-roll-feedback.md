# Landmark and Roll Feedback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make purple exclusive to real landmarks, collect landmarks reached by movement effects, and give every successful roll a consistent modal and audio response.

**Architecture:** `GameController` separates the initially triggered cell from the final resting cell, executes the initial event once, and collects the final cell only when it is a landmark. Explicit feedback metadata is persisted with each move so idempotent responses stay stable. The frontend consumes that metadata through one roll-feedback component and a dependency-free Web Audio helper.

**Tech Stack:** Laravel 12 / PHP 8.2+, SQLite, Blade, vanilla JavaScript ES modules, CSS, PHPUnit 11, Node test runner, Vite 7.

## Global Constraints

- Keep all existing landmark names and reward values.
- Purple is exclusive to `category = landmark` board cells.
- A movement destination triggers landmark collection only, never chained rewards, risks, or movement.
- Every successful move displays a modal and attempts audio feedback.
- Web Audio must support persisted mute and silent fallback.
- `prefers-reduced-motion` removes spatial and celebration motion.

---

### Task 1: Persist feedback and collect final landmarks

**Files:**
- Create: `database/migrations/2026_07_13_000400_add_feedback_to_board_moves.php`
- Modify: `app/Http/Controllers/GameController.php`
- Test: `tests/Feature/LandmarkHelpTest.php`

**Interfaces:**
- Produces response fields `feedback_type`, `final_cell_label`, `landmark_unlocked`, `landmark_count`, and `landmark_total`.
- `visitLandmark(...)` returns `['text' => string, 'unlocked' => bool]`.
- `saveMove(...)` persists `feedback_type`, `final_cell_label`, and `landmark_unlocked`.

- [ ] **Step 1: Write failing feature tests**

Add deterministic tests that make positions 1–6 movement cells ending on one landmark, then assert:

```php
$this->actingAs($user)->postJson('/activity/move', ['request_id' => (string) Str::uuid()])
    ->assertOk()
    ->assertJsonPath('feedback_type', 'landmark')
    ->assertJsonPath('final_cell_label', '测试终点地标')
    ->assertJsonPath('landmark_unlocked', true)
    ->assertJsonPath('landmark_count', 1);
$this->assertDatabaseCount('user_landmarks', 1);
```

Add a movement-to-battery test that asserts the target battery is not awarded, plus response-contract assertions for normal, boost, reward, and risk results.

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit --filter=LandmarkHelpTest
```

Expected: failures for missing feedback fields and the missing destination landmark record.

- [ ] **Step 3: Add move feedback persistence**

Create safe defaults for upgraded databases:

```php
Schema::table('board_moves', function (Blueprint $table) {
    $table->string('feedback_type')->default('normal');
    $table->string('final_cell_label')->nullable();
    $table->boolean('landmark_unlocked')->default(false);
});
```

- [ ] **Step 4: Implement final-cell settlement**

After forward/backward/bomb positioning, load `$finalCell`. Visit the initial cell when it is a landmark; otherwise visit `$finalCell` only when it is a landmark. Award only the initial cell. Persist the feedback fields and return fresh and duplicate moves through one helper that appends current landmark counts.

- [ ] **Step 5: Verify GREEN**

Run the focused test and the complete PHPUnit suite. Expected: zero failures.

### Task 2: Make purple exclusive to landmarks

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/LandmarkHelpTest.php`

**Interfaces:**
- Produces `.landmark-badge` only for `category-landmark` cells and gold styling for batteries.

- [ ] **Step 1: Write failing markup/style assertions**

Assert the board renders “地标” badges only on landmark cells and that `.cell.type-battery` does not use `var(--purple)`.

- [ ] **Step 2: Run focused tests and verify RED**

Expected: missing badge and battery-purple assertions fail.

- [ ] **Step 3: Implement semantic styling**

Render:

```blade
@if($cell->category === 'landmark')
  <span class="landmark-badge">地标</span>
@endif
```

Change battery accent to `var(--champagne)`, keep purple background/glow only in `.cell.category-landmark`, and size the badge for PC and mobile.

- [ ] **Step 4: Verify GREEN and build**

Run focused PHPUnit and `npm run build`. Expected: both exit successfully.

### Task 3: Unify roll modal and audio feedback

**Files:**
- Create: `resources/js/game-feedback.js`
- Create: `tests/js/game-feedback.test.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `package.json`

**Interfaces:**
- `feedbackPresentation(data)` returns `{ emoji, kicker, title, detail, tone, celebrate, autoCloseMs }`.
- `playFeedbackSound(kind, cellType, muted)` never throws.
- `showRollFeedback(data, onClose)` renders one modal for every successful result.

- [ ] **Step 1: Write failing Node tests**

Use table-driven cases for `normal`, `boost`, `landmark`, `reward`, and `risk`. Assert every type has a title, emoji and tone; only reward and a newly unlocked landmark celebrate; muted or unsupported audio returns without throwing.

- [ ] **Step 2: Run tests and verify RED**

Run `node --test tests/js/game-feedback.test.js`. Expected: missing module/export failure.

- [ ] **Step 3: Implement feedback mapping and sound helper**

Use short local tone sequences:

```js
const tones = {
  normal: [392, 523.25], boost: [440, 587.33, 659.25],
  landmark: [523.25, 659.25, 783.99],
  reward: [523.25, 659.25, 783.99, 1046.5], risk: [311.13, 233.08],
};
```

Create oscillators only when enabled and supported, then close the context after the last note.

- [ ] **Step 4: Replace prize-only handling**

After every successful move, call the sound and modal helpers. Remove the prize-type gate and Chinese-text parsing. Keep confetti only for rewards and new landmarks. Add an `aria-pressed` sound toggle to the board caption and persist `gameSoundMuted` in `localStorage`.

- [ ] **Step 5: Add responsive and reduced-motion styles**

Use one modal layout with normal, boost, landmark, reward and risk modifiers. Use 200–300ms entry motion; under reduced motion disable rays, confetti, floating and spatial transforms.

- [ ] **Step 6: Verify GREEN and build**

Run `npm run test:js` and `npm run build`. Expected: zero failures and Vite exit code 0.

### Task 4: Documentation and final verification

**Files:**
- Modify: `docs/features-manual.md`
- Modify: `docs/operation-manual.md`
- Modify: `docs/ai-usage-log.md`

**Interfaces:**
- Documents exact production behavior and verification evidence.

- [ ] **Step 1: Update manuals**

Document purple-only landmarks, movement-to-landmark collection, feedback on every roll, sound toggle, and the no-chain rule.

- [ ] **Step 2: Run final verification**

Run full PHPUnit with the explicit SQLite extensions, `npm run test:js`, `npm run build`, and `git diff --check`. Expected: all commands exit 0 and no whitespace errors.

- [ ] **Step 3: Review, record, commit and push**

Compare the implementation with the approved design, record actual test counts and limitations in the AI log, commit the implementation, and push `main` to `origin`.
