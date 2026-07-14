# iPhone 17 Pro Ranking Prize Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the first-place iPhone 16 Pro prize with iPhone 17 Pro everywhere users and current project documentation see it.

**Architecture:** Keep the existing configuration-driven ranking component. Change the canonical prize and asset path in `config/activity.php`, move the project-owned SVG to the new model filename, and update current tests/docs while preserving historical specs and plans.

**Tech Stack:** Laravel configuration, Blade, SVG, PHPUnit, Vite.

## Global Constraints

- Keep all second-through-tenth-place USDT rewards unchanged.
- Do not alter ranking logic, eligibility, or risk-review copy.
- Historical `docs/superpowers/specs` and `docs/superpowers/plans` remain unchanged.
- Work directly on `main` as approved.

---

### Task 1: Prize Contract and Production Update

**Files:**
- Modify: `tests/Feature/ActivityFlowTest.php`
- Modify: `tests/Feature/ExperienceCenterTest.php`
- Modify: `config/activity.php`
- Move: `public/images/ranking/iphone-16-pro.svg` to `public/images/ranking/iphone-17-pro.svg`
- Modify: `docs/features-manual.md`
- Modify: `docs/lucky-checkers-activity-plan.md`
- Modify: `design/02-pc-redesign.svg`
- Modify: `docs/ai-usage-log.md`

**Interfaces:**
- Consumes: `config('activity.ranking_rewards')` in `ranking-rewards.blade.php`.
- Produces: first reward `{ prize: 'iPhone 17 Pro', asset: 'images/ranking/iphone-17-pro.svg' }`.

- [ ] **Step 1: Write the failing tests**

Change both ranking tests to require the new prize and reject the old prize:

```php
->assertSee('iPhone 17 Pro')
->assertDontSee('iPhone 16 Pro')
->assertSee('images/ranking/iphone-17-pro.svg', false);
```

- [ ] **Step 2: Verify RED**

```bash
php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit --filter='ranking_rewards'
```

Expected: FAIL because pages still render `iPhone 16 Pro`.

- [ ] **Step 3: Implement the canonical prize change**

Set the first ranking reward to:

```php
[
    'rank' => '第 1 名',
    'prize' => 'iPhone 17 Pro',
    'asset' => 'images/ranking/iphone-17-pro.svg',
],
```

Move the SVG to the matching filename and update its accessible title. Replace the old model only in current manuals, the activity plan, and the PC design mockup.

- [ ] **Step 4: Verify GREEN and full regression suite**

```bash
php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit
npm run build
node /home/lucas/.agents/skills/impeccable/scripts/detect.mjs --json resources/views/components/ranking-rewards.blade.php design/02-pc-redesign.svg
git diff --check
```

Expected: all tests/build pass and detector returns `[]`.

- [ ] **Step 5: Record, commit, and push**

Update `docs/ai-usage-log.md` with actual red/green evidence, commit with `feat: upgrade champion prize to iPhone 17 Pro`, then push `main` to `origin`.
