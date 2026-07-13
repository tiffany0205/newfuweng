# newfuweng

基于 PHP 8.2、Laravel 12 和 SQLite 实现的 36 格大富翁活动。V2 包含用户登录注册、连续签到、每日/每周任务、圈数宝箱、邀请中心、道具背包、成就徽章、棋子皮肤、奖池播报、领奖中心、多维排行榜、消息中心及运营后台。

## 快速开始

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
npm install
npm run build
php artisan serve
```

访问 `http://127.0.0.1:8000`。演示用户为 `demo@example.com / Demo123!`，管理员为 `admin@example.com / Admin123!`。

登录后的主要入口：

- 游戏棋盘：`/activity`
- 幸运中心：`/activity/center`
- 运营后台：`/admin`

## 产品文档

- [幸运跳棋大冒险活动设计方案](docs/lucky-checkers-activity-plan.md)
- [部署文档](docs/deployment.md)
- [操作手册](docs/operation-manual.md)
