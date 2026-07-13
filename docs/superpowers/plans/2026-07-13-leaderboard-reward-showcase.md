# 排行榜奖励展示 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在游戏页和幸运中心的排行榜上方展示统一、准确且响应式的赛季奖励视觉区。

**Architecture:** 用 `config/activity.php` 维护唯一的排名奖励数据源，用 Blade 组件 `components/ranking-rewards.blade.php` 在两个页面复用同一套语义结构。原创透明背景手机产品图与 USDT 图标以 SVG 存放在项目静态资源目录，CSS 通过紧凑版和完整版修饰类适配侧栏及幸运中心。

**Tech Stack:** Laravel Blade、PHP 配置、CSS、PNG/SVG 静态资源、PHPUnit、Vite。

## Global Constraints

- 奖励严格为第 1 名 iPhone 16 Pro；第 2～5 名依次 500、400、300、200 USDT；第 6～10 名每人 100 USDT。
- 第二名只有 500 USDT，不附加其他奖品。
- 奖励只依据活动结束后的最终总进度榜及风控审核结果。
- 两个页面必须使用同一份配置数据。
- 390px 视口不得出现页面级横向滚动。
- 排名查询、排序与领奖流程保持不变。

---

### Task 1: 锁定双页面展示契约

**Files:**
- Modify: `tests/Feature/ActivityFlowTest.php`
- Modify: `tests/Feature/ExperienceCenterTest.php`

**Interfaces:**
- Consumes: `/activity` 与 `/activity/center` 已有认证页面。
- Produces: 对 `ranking-reward-showcase`、冠军名称、全部奖金和最终榜说明的渲染契约。

- [x] **Step 1: Write the failing game-page test**

新增测试，断言 `/activity` 包含 `ranking-reward-showcase compact`、`iPhone 16 Pro`、`500 USDT`、`400 USDT`、`300 USDT`、`200 USDT`、`第 6～10 名`和`每人 100 USDT`。

- [x] **Step 2: Write the failing experience-center test**

新增测试，断言 `/activity/center` 包含 `ranking-reward-showcase full`、相同奖励和`最终总进度榜`说明。

- [x] **Step 3: Run focused tests and verify RED**

Run: `php vendor/bin/phpunit tests/Feature/ActivityFlowTest.php tests/Feature/ExperienceCenterTest.php --filter=ranking_rewards`

Expected: FAIL because neither page renders `ranking-reward-showcase`.

---

### Task 2: 建立唯一奖励数据源和共享组件

**Files:**
- Create: `config/activity.php`
- Create: `resources/views/components/ranking-rewards.blade.php`
- Modify: `resources/views/game/index.blade.php`
- Modify: `resources/views/experience/center.blade.php`

**Interfaces:**
- Consumes: `config('activity.ranking_rewards')`，每项包含 `rank`、`prize`、`asset`和`type`。
- Produces: `<x-ranking-rewards variant="compact|full" />`。

- [x] **Step 1: Add the exact reward configuration**

配置包含冠军、第二至第五名和第六至第十名六档数据；冠军资源为 `/images/ranking/iphone-16-pro.svg`，现金资源为 `/images/ranking/usdt-medallion.svg`。

- [x] **Step 2: Render semantic shared markup**

组件渲染冠军主视觉、四档现金阶梯、合并奖金档和审核说明；`full`版本额外显示`以下奖励仅依据最终总进度榜`。

- [x] **Step 3: Insert the component into both leaderboard surfaces**

游戏页在 TOP 20 标题后调用 `<x-ranking-rewards variant="compact" />`；幸运中心在章节标题后调用 `<x-ranking-rewards variant="full" />`。

- [x] **Step 4: Run focused tests and verify GREEN**

Run: `php vendor/bin/phpunit tests/Feature/ActivityFlowTest.php tests/Feature/ExperienceCenterTest.php --filter=ranking_rewards`

Expected: PASS.

---

### Task 3: 制作奖品素材与响应式视觉

**Files:**
- Create: `public/images/ranking/iphone-16-pro.svg`
- Create: `public/images/ranking/usdt-medallion.svg`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: 共享组件的 `.ranking-reward-*` 类名与两个静态资源路径。
- Produces: 侧栏紧凑版、幸运中心完整版和 390px 手机布局。

- [x] **Step 1: Generate and validate the phone award image**

生成无文字、无水印、透明背景的钛金属色智能手机产品图，保存为 `public/images/ranking/iphone-16-pro.svg`，验证透明画布、完整构图和清晰边缘。

- [x] **Step 2: Create the reusable USDT medallion icon**

创建带圆形币面、T 符号和叠币层次的 SVG；不在图内写奖金数额。

- [x] **Step 3: Style hierarchy and responsive behavior**

冠军图占据主视觉；第 2～5 名桌面/手机保持两列；第 6～10 名跨满一行。紧凑版控制高度，完整版利用横向空间，所有金额保持高对比。

- [x] **Step 4: Verify browser rendering**

检查 1440×1000 和 390×844，确认两个页面无横向溢出、文字不截断、图片清晰，并验证 `prefers-reduced-motion: reduce` 下无装饰动画。

---

### Task 4: 文档、回归与交付

**Files:**
- Modify: `docs/features-manual.md`
- Modify: `docs/operation-manual.md`

**Interfaces:**
- Consumes: 已交付的双页面奖励展示。
- Produces: 与实际页面一致的用户说明及可复现验证记录。

- [x] **Step 1: Update manuals**

说明两个排行榜都会展示赛季奖励，且只有最终总进度榜决定名次奖励。

- [x] **Step 2: Run full verification**

Run: `vendor/bin/pint --test && php vendor/bin/phpunit && npm run build && composer audit && git diff --check`

Expected: 全部命令退出码为 0，无测试失败、安全公告或差异格式错误。

- [x] **Step 3: Commit and push main**

Run: `git add config/activity.php public/images/ranking resources/views/components/ranking-rewards.blade.php resources/views/game/index.blade.php resources/views/experience/center.blade.php resources/css/app.css tests/Feature/ActivityFlowTest.php tests/Feature/ExperienceCenterTest.php docs/features-manual.md docs/operation-manual.md docs/superpowers && git commit -m "feat: showcase leaderboard rewards" && git push origin main`

Expected: `main` 与 `origin/main` 指向相同的新提交。
