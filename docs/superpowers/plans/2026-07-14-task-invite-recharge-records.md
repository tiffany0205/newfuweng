# Task Invite and Recharge Records Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add private, paginated invite-registration and friend-recharge reward records to the activity task panel through one responsive dialog.

**Architecture:** `GameController` exposes one authenticated cursor endpoint whose validated `type` selects a server-owned query scoped to the active activity and current user. The activity page renders two record triggers and one reusable dialog; a focused JavaScript module owns fetching, safe DOM rendering, pagination, focus management, and close behavior.

**Tech Stack:** PHP 7.4+, Laravel, SQLite/MySQL-compatible query builder, Blade, vanilla JavaScript, CSS, PHPUnit, Node test runner, Vite.

## Global Constraints

- Keep invite registration rewards at 5 chances and first qualifying friend recharge rewards at 10 chances.
- Do not add a migration or expose email, full nickname, recharge amount, or order number.
- Return at most 10 records per page, newest reward first, and always scope queries to the authenticated user and active activity.
- Desktop uses a centered dialog; mobile uses a bottom sheet with 44px minimum touch targets.
- Support close button, backdrop click, Escape, focus restoration, loading, empty, failure/retry, and reduced motion.

---

### Task 1: Reward Record JSON API

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/GameController.php`
- Test: `tests/Feature/ActivityFlowTest.php`

**Interfaces:**
- Produces: `GET /activity/task-reward-records?type=invite|friend_recharge&cursor=<id>` returning `{data, next_cursor, has_more}`.
- Each data item is `{id:int, friend_name:string, occurred_at:string, chance_awarded:int}`.

- [ ] **Step 1: Write failing feature tests**

Insert invitation fixtures for the current and another inviter, plus `invite_register` and `friend_recharge` chance transactions. Assert the endpoint returns only the current user's masked friends, uses transaction timestamps and amounts, excludes unqualified recharge records, returns 10 rows, and rejects invalid `type`/`cursor` values.

- [ ] **Step 2: Verify RED**

Run:

```bash
php vendor/bin/phpunit --filter='task_reward_records'
```

Expected: FAIL because route `game.records.task-rewards` does not exist.

- [ ] **Step 3: Implement the minimal route and controller query**

Add the authenticated route and `taskRewardRecords(Request $request): JsonResponse`. Validate with:

```php
['type' => ['required', Rule::in(['invite', 'friend_recharge'])], 'cursor' => ['nullable', 'integer', 'min:1']]
```

Build one base query joining `invitation_rewards` to invitee `users`, then join the expected chance transaction with the activity/user/type/business-key constraints. For invite rows, use invitation time and `COALESCE(transaction.amount, 5)`; for friend recharge rows require both `recharge_awarded` and a matching reward transaction. Apply `id < cursor`, newest ID first, fetch 11, return 10 plus the next cursor.

- [ ] **Step 4: Verify GREEN**

Run the same focused PHPUnit command and expect all reward record endpoint tests to pass.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/GameController.php tests/Feature/ActivityFlowTest.php
git commit -m "feat: add task reward record API"
```

### Task 2: Task Panel Dialog Markup and Styling

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/ActivityFlowTest.php`

**Interfaces:**
- Consumes: route `game.records.task-rewards`.
- Produces: triggers with `[data-task-records]` and dialog `#taskRewardDialog` containing status, list, retry, load-more, and close controls.

- [ ] **Step 1: Write a failing render test**

Assert the activity HTML contains both `data-task-records="invite"` and `data-task-records="friend_recharge"`, the endpoint URL, `role="dialog"`, `aria-modal="true"`, and the shared list/status/load-more hooks.

- [ ] **Step 2: Verify RED**

Run the focused render test and expect failure because the task record UI is absent.

- [ ] **Step 3: Add minimal semantic markup and responsive CSS**

Keep the copy button, add “邀请记录” and “达标记录” buttons, show separate invite and qualified recharge counts, then add the single hidden dialog after the main layout. Style centered desktop presentation, mobile bottom-sheet presentation, timeline rows, clear empty/error states, focus-visible controls, and reduced-motion overrides using the existing color tokens.

- [ ] **Step 4: Verify GREEN and scan UI rules**

Run the focused PHPUnit render test, then:

```bash
node /home/lucas/.agents/skills/impeccable/scripts/detect.mjs --json resources/views/game/index.blade.php resources/css/app.css
```

Expected: render test passes and detector returns no new violations.

- [ ] **Step 5: Commit**

```bash
git add resources/views/game/index.blade.php resources/css/app.css tests/Feature/ActivityFlowTest.php
git commit -m "feat: add task reward record dialog"
```

### Task 3: Accessible Dialog Client Behavior

**Files:**
- Create: `resources/js/task-reward-records.js`
- Create: `tests/js/task-reward-records.test.mjs`
- Modify: `resources/js/app.js`

**Interfaces:**
- Produces: `initTaskRewardRecords(document, fetch)` and pure `taskRecordCopy(type)` helpers.
- Consumes: JSON API fields defined in Task 1 and DOM hooks defined in Task 2.

- [ ] **Step 1: Write failing Node tests**

Test type-specific title/copy, safe row creation through `textContent`, empty-state selection, pagination state, and initialization behavior against a minimal DOM fixture.

- [ ] **Step 2: Verify RED**

Run:

```bash
node --test tests/js/task-reward-records.test.mjs
```

Expected: FAIL with module-not-found for `task-reward-records.js`.

- [ ] **Step 3: Implement client module and initialize it from app.js**

Fetch the first page on trigger click; clear stale rows when types change; append rows on load more; display retry on request failure; open/close with focus restoration, Escape, backdrop click and body scroll locking. Render all server data with DOM `textContent`, never `innerHTML`.

- [ ] **Step 4: Verify GREEN**

Run the focused Node test and Vite build. Expect tests and production compilation to pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/task-reward-records.js resources/js/app.js tests/js/task-reward-records.test.mjs
git commit -m "feat: power task reward record dialog"
```

### Task 4: Documentation, Full Verification, and Delivery

**Files:**
- Modify: `docs/features-manual.md`
- Modify: `docs/ai-usage-log.md`

**Interfaces:**
- Documents the final user flow and measured verification evidence.

- [ ] **Step 1: Update the operating manual and AI record**

Document where users open each list, what every field means, the 10-row loading behavior, privacy masking, empty/error handling, and the fact that friend recharge is rewarded once. Update the AI log with red/green evidence and final command results only after they occur.

- [ ] **Step 2: Run full verification**

Run Pint, full PHPUnit, full Node tests, Vite build, Composer audit, interface detector, `git diff --check`, and a repository search confirming no private friend email or recharge-order fields appear in the dialog markup/JSON.

- [ ] **Step 3: Review requirements and commit**

Check every global constraint and spec section against the diff, then commit documentation and any final corrections:

```bash
git add docs/features-manual.md docs/ai-usage-log.md
git commit -m "docs: explain task reward records"
```

- [ ] **Step 4: Push and confirm remote state**

```bash
git push origin main
git status --short --branch
git rev-parse HEAD
git rev-parse origin/main
```

Expected: clean `main...origin/main` and identical commit hashes.
