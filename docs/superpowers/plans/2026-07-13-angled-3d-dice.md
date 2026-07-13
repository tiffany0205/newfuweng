# Angled 3D Dice Implementation Plan

> **For Codex:** Execute this plan task by task with tests first. Keep all game probability, movement, reward, sound, and result-modal behavior unchanged.

**Goal:** Make the board-center idle die read as a tilted three-face 3D object with independent breathing motion and a matching shadow on desktop and mobile.

**Architecture:** Keep the existing six CSS cube faces and server-result `face-N` classes. Add a presentation-only floating shell around the cube, move idle animation from the button to that shell, and give every result class a tilted resting pose. JavaScript and backend roll logic remain untouched.

**Tech Stack:** Laravel Blade, CSS 3D transforms/keyframes, PHPUnit, Vite.

---

### Task 1: Lock the presentation contract with a failing feature test

**Files:**
- Create: `tests/Feature/DicePresentationTest.php`
- Test: `tests/Feature/DicePresentationTest.php`

**Step 1: Write the failing test**

Add assertions that the activity Blade contains an independent `dice-float-shell`, the prompt remains outside it, the stylesheet defines six tilted result poses, dedicated idle/shadow keyframes, rolling-state suspension, and reduced-motion handling.

**Step 2: Run the focused test to verify it fails**

Run:

```bash
php -d extension=/usr/lib/php/20230831/sqlite3.so -d extension=/usr/lib/php/20230831/pdo_sqlite.so vendor/bin/phpunit --filter=DicePresentationTest
```

Expected: FAIL because `dice-float-shell` and the new motion rules do not exist yet.

### Task 2: Implement the tilted cube and independent breathing layer

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/DicePresentationTest.php`

**Step 1: Add the floating shell markup**

Wrap only `dice-cube` in `dice-float-shell`; keep `dice-shadow` beside it and `dice-prompt` outside `dice-stage`.

**Step 2: Add six tilted resting poses**

Replace flat result transforms with explicit X/Y/Z rotations that preserve the selected front face while revealing top and right-side depth.

**Step 3: Add synchronized idle and shadow motion**

Animate the shell with gentle vertical/scale breathing and animate the shadow inversely. Remove idle animation from the full trigger so prompt text stays still.

**Step 4: Reconcile rolling, pressed, mobile, and reduced-motion states**

Pause shell/shadow breathing during rolling, begin the tumble from a tilted pose, scale motion for mobile, and disable motion without flattening the cube when reduced motion is requested.

**Step 5: Run the focused test to verify it passes**

Run the Task 1 command.

Expected: PASS.

### Task 3: Document, audit, and verify the complete change

**Files:**
- Modify: `docs/操作手册.md` (or the repository's existing operation-manual file)
- Modify: `docs/ai-usage-log.md`

**Step 1: Update the user-facing manual and AI usage record**

Describe the angled idle die, click behavior, mobile behavior, reduced-motion fallback, human-approved design choice, implementation scope, and verification evidence.

**Step 2: Run the interface quality audit**

Run:

```bash
node /home/lucas/.agents/skills/impeccable/scripts/detect.mjs --json resources/views/game/index.blade.php resources/css/app.css
```

Expected: no new findings.

**Step 3: Run full automated verification**

Run:

```bash
php -d extension=/usr/lib/php/20230831/sqlite3.so -d extension=/usr/lib/php/20230831/pdo_sqlite.so vendor/bin/phpunit
npm test
npm run build
```

Expected: all PHPUnit and JavaScript tests pass; Vite production build succeeds.

**Step 4: Review the diff for scope and regressions**

Confirm there are no changes to controllers, services, routes, random point generation, rewards, sound, or result-modal JavaScript.

**Step 5: Commit and push directly to `main`**

Commit the implementation and documentation, then push `main` to `origin` as explicitly requested by the user.
