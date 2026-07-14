# 排行榜冠军奖品升级为 iPhone 17 Pro

## 目标

将当前活动最终总进度榜第一名奖品从 `iPhone 16 Pro` 统一升级为 `iPhone 17 Pro`，不调整第二至第十名的 USDT 奖励、获奖范围、排行规则或风控说明。

## 实现范围

- 生产配置 `config/activity.php` 的冠军奖品名称改为 `iPhone 17 Pro`。
- 冠军奖品图使用新的 `images/ranking/iphone-17-pro.svg` 路径；沿用现有无文字、透明背景、旗舰三摄手机的项目自有 SVG 视觉，不嵌入型号文字或第三方水印。
- 排行榜组件继续从配置生成标题和图片 `alt`，因此游戏页紧凑版和幸运中心完整版同步更新。
- 当前功能说明、活动方案和 PC 设计稿中的冠军奖品同步更新。
- PHP 回归测试改为断言 `iPhone 17 Pro`，并明确页面不再出现 `iPhone 16 Pro`。
- `docs/superpowers/specs` 和 `docs/superpowers/plans` 中已经完成的历史设计记录保留原文，避免篡改当时的决策记录。

## 验收

- `/activity` 与 `/activity/center` 均显示 `iPhone 17 Pro`。
- 两个页面的奖品图片地址为 `images/ranking/iphone-17-pro.svg`，替代文本为 `iPhone 17 Pro 奖品图`。
- 当前生产配置、当前手册和设计稿中不再使用旧型号。
- 第 2 名 500 USDT、第 3 名 400 USDT、第 4 名 300 USDT、第 5 名 200 USDT、第 6～10 名每人 100 USDT 保持不变。
- PHPUnit、Vite 构建和界面规范扫描通过。
