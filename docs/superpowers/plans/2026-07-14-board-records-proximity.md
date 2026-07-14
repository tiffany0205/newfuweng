# Board Records Proximity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Place opportunity and winning records directly below the board without allowing the taller task/ranking sidebar to create empty vertical space.

**Architecture:** Wrap the existing game area and records section in a new `game-column` flex container. Keep the sidebar as the second dashboard grid item so both columns flow independently; collapse the dashboard and records grids at existing responsive breakpoints.

**Tech Stack:** Laravel Blade, CSS Grid/Flexbox, PHPUnit, Vite, impeccable layout detector.

## Global Constraints

- Keep board dimensions, record data, default-open behavior, 10-row pagination, task content, and ranking content unchanged.
- Use 16px board-to-record spacing on PC and 12px on narrow screens.
- Keep two record columns on PC and one column on mobile.

---

### Task 1: Restructure the Activity Layout

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/ActivityFlowTest.php`

- [ ] Write a failing render test that locates `class="game-column"`, confirms the records section occurs inside it after `game-area`, and confirms `side-panel` occurs after the closing game column.
- [ ] Run the focused PHPUnit test and verify failure because `game-column` does not exist.
- [ ] Wrap `game-area` and the unchanged records section in `.game-column`; keep `.side-panel` as the second `.dashboard` child. Add `display:flex; flex-direction:column; gap:16px` and scope `.records` to the left column without page-level width/padding/margins.
- [ ] At the existing narrow breakpoint, set the dashboard to one column, use a 12px left-column gap, and make `.records` one column so it renders before the sidebar.
- [ ] Run the focused test, record pagination tests, Vite build, and layout detector; expect all to pass and the detector to return no findings.

### Task 2: Documentation and Delivery

**Files:**
- Modify: `docs/features-manual.md`
- Modify: `docs/ai-usage-log.md`

- [ ] Document the new PC/mobile content order and update the AI log with actual red/green evidence.
- [ ] Run Pint, full PHPUnit, full Node tests, Vite build, Composer audit, layout/interface detector, and `git diff --check`.
- [ ] Review the diff against global constraints, commit implementation and documentation, push `main`, and verify local and remote hashes match.
