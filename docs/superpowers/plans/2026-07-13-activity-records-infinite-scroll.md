# 活动记录滑动分页 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 让机会明细和中奖列表默认展开、首批各展示 10 条，并在用户滑动到底部时各自加载下一批 10 条。

**Architecture:** `GameController` 使用基于 ID 的游标分页渲染首批记录并提供两个认证 JSON 接口。Blade 为两个表格输出统一的加载器数据属性，原生 JavaScript 以 `IntersectionObserver` 和按钮回退独立维护加载状态、去重并安全追加行。

**Tech Stack:** Laravel、SQLite、Blade、原生 JavaScript、IntersectionObserver、PHPUnit、Vite。

## Global Constraints

- 两个列表默认同时展开。
- 首屏和每次后续请求固定为 10 条。
- 按 `id DESC` 查询，后续使用 `id < cursor`。
- 接口只能返回当前登录用户在当前活动中的记录。
- 两个列表加载状态完全独立。
- 加载失败必须可点击重试，不支持 IntersectionObserver 时必须保留按钮加载。
- 动态文本通过 DOM `textContent` 写入，不拼接未转义接口数据。
- 手机端使用页面自然滚动，不增加嵌套滚动容器。

---

### Task 1: 锁定首屏与接口契约

**Files:**
- Modify: `tests/Feature/ActivityFlowTest.php`

**Interfaces:**
- Consumes: 已登录用户、`chance_transactions`和`winning_records`测试数据。
- Produces: 默认展开、10 条首屏、游标分页、用户隔离和无效游标的回归测试。

- [x] **Step 1: Add the failing initial-render test**

创建超过 10 条机会和中奖记录，请求`/activity`，断言`机会明细`与`中奖列表`所在的两个`details`都含`open`，并且 HTML 中两类`data-record-id`各出现 10 次。

- [x] **Step 2: Add the failing pagination endpoint test**

以当前用户创建 25 条记录、其他用户创建带唯一文案的记录；请求两个新接口并断言每批 10 条、ID 倒序、`has_more`和`next_cursor`正确，响应不包含其他用户文案。

- [x] **Step 3: Add invalid-cursor coverage**

请求`cursor=0`，断言两个接口均返回 422。

- [x] **Step 4: Run focused tests and verify RED**

Run: `php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit tests/Feature/ActivityFlowTest.php --filter=records`

Expected: FAIL because routes and initial 10-row rendering do not exist.

---

### Task 2: 实现服务端游标分页

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/GameController.php`

**Interfaces:**
- Consumes: `GET /activity/records/chances?cursor={positive-int}` and `GET /activity/records/winnings?cursor={positive-int}`。
- Produces: `{data: array, next_cursor: int|null, has_more: bool}`。

- [x] **Step 1: Register authenticated GET routes**

添加命名路由`game.records.chances`和`game.records.winnings`，分别指向`chanceRecords`与`winningRecords`。

- [x] **Step 2: Add a reusable private cursor query**

新增`recordPage(string $table, int $activityId, int $userId, ?int $cursor = null): array`，按 ID 倒序、可选`id < cursor`、读取 11 条并返回前 10 条、下一游标和是否有更多。

- [x] **Step 3: Render the initial page from the same query**

`index`使用`recordPage`分别获得机会与中奖首批数据，将`transactions`、`winnings`、`transactionCursor`、`winningCursor`、`hasMoreTransactions`和`hasMoreWinnings`传给 Blade。

- [x] **Step 4: Return shaped JSON and validate cursors**

两个接口使用`$request->validate(['cursor' => ['required','integer','min:1']])`；机会接口返回机会字段，中奖接口将状态映射为`已发放`或`待发放`。

- [x] **Step 5: Run focused tests and verify the API behavior**

Run: `php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit tests/Feature/ActivityFlowTest.php --filter=records`

Expected: API assertions pass; initial-render assertion may remain red until Task 3.

---

### Task 3: 实现默认展开与独立滑动加载

**Files:**
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/js/app.js`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: `.record-loader[data-record-type][data-url][data-cursor][data-has-more]` and API response from Task 2.
- Produces: 两个默认展开表格、独立自动加载、按钮回退、去重和状态反馈。

- [x] **Step 1: Make both details open and render loader metadata**

两个`details`均添加`open`；每行输出`data-record-id`和对应类型类名；非空列表下方输出带接口 URL、游标、是否有更多、`aria-live="polite"`和按钮的`.record-loader`。

- [x] **Step 2: Implement one reusable JavaScript loader**

遍历所有`.record-loader`，从已有行建立 ID 集合；按钮点击或哨兵进入视口时 fetch 下一页，使用`createElement`和`textContent`构造机会或中奖行，更新游标与完成状态，并通过`loading`布尔值阻止重复请求。

- [x] **Step 3: Add failure and compatibility behavior**

请求失败后按钮显示`加载失败，点击重试`并重新启用；没有 IntersectionObserver 时不隐藏按钮，用户仍可手动加载。

- [x] **Step 4: Style clear, compact loader states**

加载按钮使用现有中性色与香槟金聚焦态，最小高度 40px；完成状态弱化但保持可读，不添加装饰动画或内部滚动条。

- [x] **Step 5: Run focused tests and verify GREEN**

Run: `php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit tests/Feature/ActivityFlowTest.php --filter=records`

Expected: PASS.

---

### Task 4: 文档、浏览器验证与交付

**Files:**
- Modify: `docs/features-manual.md`
- Modify: `docs/operation-manual.md`

**Interfaces:**
- Consumes: 已完成的双列表分页交互。
- Produces: 用户说明、浏览器验证和可审计交付提交。

- [x] **Step 1: Update manuals**

说明两个列表默认展开、首批 10 条、滑到底部自动加载下一批 10 条及失败重试行为。

- [x] **Step 2: Verify browser interaction**

在桌面与 390px 手机视口分别检查两个列表默认展开；准备超过 20 条测试数据，确认两个列表独立追加、无重复 ID、完成状态正确且无页面横向溢出。

- [x] **Step 3: Run full verification**

Run: `vendor/bin/pint --test && php -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/sqlite3.so -d extension=/tmp/php-sqlite-ext/extracted/usr/lib/php/20250925/pdo_sqlite.so vendor/bin/phpunit && npm run build && composer audit && git diff --check`

Expected: 全部命令退出码为 0，无测试失败、安全公告或差异格式错误。

- [x] **Step 4: Commit and push main**

Run: `git add app/Http/Controllers/GameController.php routes/web.php resources/views/game/index.blade.php resources/js/app.js resources/css/app.css tests/Feature/ActivityFlowTest.php docs/features-manual.md docs/operation-manual.md docs/superpowers/plans/2026-07-13-activity-records-infinite-scroll.md && git commit -m "feat: paginate activity records on scroll" && git push origin main`

Expected: `main`与`origin/main`指向同一新提交。
