# 中奖状态高亮 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在活动页中奖列表中用一致、易识别的语义胶囊区分待发放和已发放状态。

**Architecture:** Blade 负责首屏语义标记，分页接口返回归一化机器状态，现有安全 DOM 分页加载器负责创建后续状态胶囊。CSS 提供待发放和已发放两种克制的语义色。

**Tech Stack:** Laravel、Blade、原生 JavaScript、CSS、PHPUnit、Vite

## Global Constraints

- 所有非 `issued` 状态在用户活动页统一显示为“待发放”。
- 不改变中奖发放业务、分页数量和数据排序。
- 状态不能只依赖颜色表达，不增加装饰动画。

---

### Task 1: 状态数据与首屏语义结构

**Files:**
- Modify: `tests/Feature/ActivityFlowTest.php`
- Modify: `app/Http/Controllers/GameController.php`
- Modify: `resources/views/game/index.blade.php`

**Interfaces:**
- Produces: 分页记录的 `status: issued|pending` 与首屏 `.winning-status--issued|pending`。

- [ ] **Step 1: Write the failing test**

断言首屏包含两种状态胶囊，并断言分页 JSON 返回归一化 `status`。

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/ActivityFlowTest.php --filter=winning_status`
Expected: FAIL，页面缺少状态胶囊。

- [ ] **Step 3: Write minimal implementation**

Blade 输出带图标和文字的状态胶囊；控制器在分页响应增加归一化状态。

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/ActivityFlowTest.php --filter=winning_status`
Expected: PASS。

### Task 2: 动态分页样式与视觉系统

**Files:**
- Modify: `resources/js/app.js`
- Modify: `resources/css/app.css`
- Modify: `docs/features-manual.md`
- Modify: `docs/ai-usage-log.md`

**Interfaces:**
- Consumes: 分页记录的 `status` 与 `status_label`。
- Produces: 与首屏一致的动态 `.winning-status` DOM。

- [ ] **Step 1: Implement safe DOM rendering**

使用 `createElement` 和 `textContent` 创建图标及标签，不注入接口 HTML。

- [ ] **Step 2: Add responsive semantic styles**

添加紧凑的基础胶囊、香槟金待发放和绿色已发放样式。

- [ ] **Step 3: Verify feature and build**

Run: `php vendor/bin/phpunit tests/Feature/ActivityFlowTest.php && npm run build`
Expected: 测试和构建通过。

- [ ] **Step 4: Run full verification and commit**

Run: `php vendor/bin/phpunit && npm run test:js && vendor/bin/pint --test && composer audit && git diff --check`
Expected: 全部命令退出码为 0；随后提交并推送 `main`。
