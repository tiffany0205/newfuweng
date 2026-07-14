# 管理后台任务与奖品发放修复 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 修复旧库任务为空，并将后台领奖与发放整合为可理解的统一队列。

**Architecture:** 数据迁移按业务键补齐任务定义；后台以 `winning_records` 左连接 `prize_claims`，使未提交和已提交记录进入同一视图。现有发放与审核控制器动作继续复用，减少业务风险。

**Tech Stack:** Laravel、Blade、SQLite、PHPUnit、CSS

## Global Constraints

- 不覆盖已有任务配置。
- 不删除历史中奖或领奖申请。
- 领奖资料只向管理员展示。
- 已发放记录不得重复操作。

---

### Task 1: 旧库任务补偿迁移

**Files:**
- Create: `database/migrations/2026_07_14_000700_sync_default_task_definitions.php`
- Create: `tests/Feature/TaskDefinitionUpgradeTest.php`

**Interfaces:**
- Produces: 每个已有活动按 `code` 拥有 6 条默认任务。

- [ ] 写测试：删除任务后执行迁移恢复 6 条，保留已有自定义项，重复执行不增加记录。
- [ ] 运行专项测试并确认因迁移不存在而失败。
- [ ] 使用 `updateOrInsert` 的插入保护实现迁移，只补缺失业务键。
- [ ] 运行专项测试并确认通过。

### Task 2: 统一奖品发放管理

**Files:**
- Modify: `app/Http/Controllers/AdminController.php`
- Modify: `resources/views/admin/index.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/ExperienceCenterTest.php`

**Interfaces:**
- Consumes: 当前活动中奖记录及可选的领奖申请。
- Produces: 单一“奖品发放管理”列表，区分等待提交、审核流程和已发放。

- [ ] 写测试：后台同时展示无申请和已提交申请，且旧的两个模块标题消失。
- [ ] 运行测试并确认旧页面结构导致失败。
- [ ] 将后台查询改为中奖记录左连接申请，并限定当前活动。
- [ ] 重写 Blade 列表与响应式样式，继续复用现有两个处理路由。
- [ ] 运行后台与体验中心专项测试并确认通过。

### Task 3: 文档与完整验证

**Files:**
- Modify: `docs/operation-manual.md`
- Modify: `docs/features-manual.md`
- Modify: `docs/ai-usage-log.md`

**Interfaces:**
- Produces: 与实际后台一致的操作步骤和验证记录。

- [ ] 更新任务管理和统一发放操作说明。
- [ ] 运行 PHPUnit、Node 测试、Pint、Vite 构建、Composer audit 和界面规则扫描。
- [ ] 提交并推送 `main`。
