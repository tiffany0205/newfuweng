# newfuweng

基于 PHP 8.2、Laravel 12 和 SQLite 实现的 36 格大富翁活动。包含用户登录注册、连续签到、邀请、充值奖励、棋盘事件、机会流水、中奖记录、实时排行榜及运营后台。

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

## 产品文档

- [幸运跳棋大冒险活动设计方案](docs/lucky-checkers-activity-plan.md)
- [部署文档](docs/deployment.md)
- [操作手册](docs/operation-manual.md)
