# Landmark Backfill and Position Display Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Repair landmark configuration and missed historical progress in existing databases, then show every roll's actual 1-based destination consistently in records and UI.

**Architecture:** A new idempotent data migration owns existing-database repair and deterministic history backfill. `GameController` owns the canonical move transaction description and returns a display-ready position/record contract. Blade and JavaScript consume that contract without changing stored zero-based positions or ranking order.

**Tech Stack:** PHP 8.5, Laravel 12, SQLite, Blade, vanilla JavaScript, Node test runner, Vite.

## Global Constraints

- Keep database positions zero-based (`0..35`) and expose user positions as `1..36`.
- Do not change dice randomness, rewards, lap calculation, ranking order, sound, or modal trigger rules.
- Backfill deterministic landmark count and lucky points only; do not recreate random rainbow outcomes or consumable effects.
- Existing move requests and the repair migration must remain idempotent.
- Work directly on `main` as explicitly approved by the user.

---

### Task 1: Existing Database Landmark Repair

**Files:**
- Create: `database/migrations/2026_07_14_000500_sync_landmarks_and_move_positions.php`
- Create: `tests/Feature/LandmarkUpgradeTest.php`

**Interfaces:**
- Consumes: existing `activities`, `board_cells`, `board_moves`, `activity_users`, `user_landmarks`, `chance_transactions`, `item_definitions`, `landmark_reward_definitions`, and `faq_entries` tables.
- Produces: canonical 12-landmark configuration; backfilled `user_landmarks`; deterministic `lucky_points`; repaired historical move remarks.

- [ ] **Step 1: Write the failing old-database upgrade test**

Create a seeded test state, downgrade all board categories to `safe`, remove landmark records/definitions, insert two historical moves ending at position 16, and insert matching “跳棋消耗” rows. Load the new migration and assert:

```php
$migration = require database_path('migrations/2026_07_14_000500_sync_landmarks_and_move_positions.php');
$migration->up();

$star = DB::table('board_cells')->where('position', 16)->first();
$this->assertSame('landmark', $star->category);
$this->assertSame('starlight_square', $star->landmark_code);
$this->assertSame('lucky_2', $star->effect_code);
$this->assertSame(2, DB::table('user_landmarks')->where('board_cell_id', $star->id)->value('visit_count'));
$this->assertSame(5, DB::table('activity_users')->where('user_id', $user->id)->value('lucky_points'));
$this->assertSame('掷出 6 点 · 到达第 17 格 星光广场', $remark);
```

The expected five points are two points per star visit plus one repeat-visit point.

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit --filter=LandmarkUpgradeTest
```

Expected: FAIL because the `000500` migration does not exist.

- [ ] **Step 3: Implement the idempotent repair migration**

Define the canonical landmark map by position:

```php
$landmarks = [
    3 => ['travel_station', 'free_roll', '首次到达解锁旅行驿站印章；到达后返还本次机会。'],
    5 => ['sunshine_road', 'freeze_guard', '首次到达解锁阳光大道印章；获得一次冰冻免疫。'],
    8 => ['rainbow_district', 'rainbow', '首次到达解锁彩虹街区印章；随机获得小惊喜。'],
    10 => ['happy_station', 'free_roll', '首次到达解锁欢乐车站印章；到达后返还本次机会。'],
    13 => ['lucky_corner', 'high_roll', '首次到达解锁幸运转角印章；下一次骰子最低为 4 点。'],
    16 => ['starlight_square', 'lucky_2', '首次到达解锁星光广场印章；获得 2 点幸运值。'],
    19 => ['seaside_road', 'lucky_1', '首次到达解锁海滨大道印章；获得 1 点幸运值。'],
    22 => ['music_town', 'reroll', '首次到达解锁音乐小镇印章；获得一次重掷效果。'],
    25 => ['forest_park', 'shield', '首次到达解锁森林公园印章；首次解锁额外获得防护盾。'],
    28 => ['dream_harbor', 'lucky_2', '首次到达解锁梦想港湾印章；获得 2 点幸运值。'],
    31 => ['golden_road', 'lucky_2', '首次到达解锁金色大道印章；获得 2 点幸运值。'],
    35 => ['finish_sprint', 'free_roll', '首次到达解锁终点冲刺印章；到达后返还本次机会。'],
];
```

For every activity, reset category from `type`, update these positions, ensure `reroll`/`lucky` item definitions and four landmark reward definitions, and insert the landmark FAQ entries with `updateOrInsert`.

Group historical moves by activity/user/position. Skip groups with an existing `user_landmarks` row. Insert visits using historical min/max timestamps and add:

```php
$points = max(0, $visits - 1);
if (str_starts_with($effectCode, 'lucky_')) {
    $points += $visits * (int) str_replace('lucky_', '', $effectCode);
}
```

Finally update every matching historical move transaction using `move-{request_id}` and `to_position + 1`.

- [ ] **Step 4: Run the focused test and verify GREEN**

Run the Step 2 command. Expected: PASS, including a second `up()` call that does not duplicate landmarks or lucky points.

- [ ] **Step 5: Commit Task 1**

```bash
git add database/migrations/2026_07_14_000500_sync_landmarks_and_move_positions.php tests/Feature/LandmarkUpgradeTest.php
git commit -m "fix: repair landmark data and history"
```

### Task 2: Canonical Move Position and Transaction Contract

**Files:**
- Modify: `app/Http/Controllers/GameController.php`
- Modify: `tests/Feature/ActivityFlowTest.php`
- Modify: `tests/Feature/LandmarkHelpTest.php`

**Interfaces:**
- Consumes: `board_moves.request_id`, zero-based `to_position`, and `final_cell_label`.
- Produces: `display_position: int`, `lucky_points: int`, and `chance_transaction: {id, created_at, remark, amount, balance_after}` in every successful move response.

- [ ] **Step 1: Write failing response and transaction tests**

Extend the idempotency test to require:

```php
->assertJsonPath('display_position', fn (int $value) => $value >= 1 && $value <= 36)
->assertJsonPath('chance_transaction.remark', fn (string $remark) => str_contains($remark, '到达第 '));
```

Force positions 1～6 to forward to the real star at position 16, then assert the first visit returns `display_position = 17`, `final_cell_label = 星光广场`, `lucky_points = 2`, and a remark matching `掷出 N 点 · 到达第 17 格 星光广场`. Add a frozen-state test expecting `解冻成功 · 停留第 N 格`.

- [ ] **Step 2: Run focused tests and verify RED**

```bash
php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit --filter='move.*position|star.*lucky|frozen.*position'
```

Expected: FAIL because the response lacks `display_position`, `lucky_points`, and `chance_transaction`, while remarks remain generic.

- [ ] **Step 3: Add canonical remark helpers and response fields**

Add focused private methods:

```php
private function moveRemark(object $move): string
{
    $position = (int) $move->to_position + 1;
    $label = $move->final_cell_label ?: '当前位置';

    return $move->action_type === 'unfreeze'
        ? "解冻成功 · 停留第 {$position} 格 {$label}"
        : "掷出 {$move->dice_value} 点 · 到达第 {$position} 格 {$label}";
}

private function syncMoveTransaction(object $move): object
{
    DB::table('chance_transactions')->where([
        'activity_id' => $move->activity_id,
        'user_id' => $move->user_id,
        'business_key' => 'move-'.$move->request_id,
    ])->update(['remark' => $this->moveRemark($move), 'updated_at' => now()]);

    return DB::table('chance_transactions')->where('business_key', 'move-'.$move->request_id)->firstOrFail();
}
```

Call the synchronizer after every `saveMove`. In `moveResponse`, set `display_position`, `lucky_points`, and a normalized `chance_transaction` object. Preserve duplicate-request behavior by reading the same stored move and transaction.

- [ ] **Step 4: Run focused tests and verify GREEN**

Run the Step 2 command. Expected: PASS.

- [ ] **Step 5: Run all PHP tests for regression safety**

```bash
php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 6: Commit Task 2**

```bash
git add app/Http/Controllers/GameController.php tests/Feature/ActivityFlowTest.php tests/Feature/LandmarkHelpTest.php
git commit -m "fix: record actual move destinations"
```

### Task 3: One-Based Position UI and Immediate Record Feedback

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/views/experience/center.blade.php`
- Modify: `resources/js/app.js`
- Modify: `resources/js/game-feedback.js`
- Modify: `tests/js/game-feedback.test.js`
- Modify: `tests/Feature/ActivityFlowTest.php`
- Modify: `tests/Feature/ExperienceCenterTest.php`
- Modify: `docs/operation-manual.md`
- Modify: `docs/ai-usage-log.md`

**Interfaces:**
- Consumes: Task 2 response fields `display_position`, `final_cell_label`, `lucky_points`, and `chance_transaction`.
- Produces: consistent visible 1～36 positions in stats, event rail, modal, rankings, and chance records.

- [ ] **Step 1: Write failing JavaScript and Blade contract tests**

Add a Node assertion:

```js
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
```

Feature tests assert the game stat and both progress leaderboards render `current_position + 1`, and the source contains `prependChanceRecord(data.chance_transaction)`.

- [ ] **Step 2: Run focused tests and verify RED**

```bash
npm run test:js
php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit --filter='position_display|ranking_positions'
```

Expected: FAIL because modal details and several templates still expose zero-based values.

- [ ] **Step 3: Implement clear one-based UI copy**

Use `{{ $state->current_position + 1 }}` and `{{ $item->current_position + 1 }}` in Blade. In the move handler use `data.display_position` for the top stat, center status, and event rail:

```js
eventEl.textContent = `第 ${data.display_position} 格 · ${data.result_text}`;
document.querySelector('#position').textContent = data.display_position;
if (centerPosition) centerPosition.textContent = data.display_position;
prependChanceRecord(data.chance_transaction);
```

Create a shared DOM helper that removes the empty-state row, deduplicates by `data-record-id`, and prepends the normalized chance record to the chance table. Keep infinite-scroll append behavior unchanged.

Update `feedbackPresentation` so every result contains `第 N 格 · 格子名称`; landmark detail additionally contains `地标 X / Y · 幸运值 Z`.

- [ ] **Step 4: Run focused tests and verify GREEN**

Run the Step 2 commands. Expected: PASS.

- [ ] **Step 5: Update user and AI documentation**

Document that positions are always 1～36, chance records include rolled points/destination, old missed landmarks are repaired during migration, and only deterministic historical lucky points are compensated. Record actual red/green results and human approval in `docs/ai-usage-log.md`.

- [ ] **Step 6: Run complete verification**

```bash
vendor/bin/pint --test
php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit
npm run test:js
npm run build
node /home/lucas/.agents/skills/impeccable/scripts/detect.mjs --json resources/views/game/index.blade.php resources/views/experience/center.blade.php resources/js/app.js
composer audit --no-interaction
git diff --check
```

Expected: all commands exit successfully; the interface detector returns `[]`.

- [ ] **Step 7: Commit and push**

```bash
git add resources/views/game/index.blade.php resources/views/experience/center.blade.php resources/js/app.js resources/js/game-feedback.js tests/js/game-feedback.test.js tests/Feature/ActivityFlowTest.php tests/Feature/ExperienceCenterTest.php docs/operation-manual.md docs/ai-usage-log.md docs/superpowers/plans/2026-07-14-landmark-backfill-and-position-display.md
git commit -m "feat: show actual positions across activity"
git push origin main
```
