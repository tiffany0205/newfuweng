# 幸运跳棋大冒险部署文档

## 1. 环境要求

- Ubuntu 22.04/24.04 或同类 Linux。
- PHP 8.2+，扩展：`cli`、`fpm`、`sqlite3`、`mbstring`、`xml`、`curl`、`zip`、`bcmath`。
- Composer 2.x。
- Node.js 20+ 和 npm，仅在构建前端资源时需要。
- Nginx。
- 本地磁盘至少保留 1GB 可用空间用于程序、SQLite、日志和备份。

Ubuntu 示例：

```bash
sudo apt update
sudo apt install -y nginx php8.2-cli php8.2-fpm php8.2-sqlite3 php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath unzip
```

## 2. 获取与安装项目

```bash
sudo mkdir -p /var/www/newfuweng
sudo chown -R "$USER":www-data /var/www/newfuweng
git clone git@github.com:tiffany0205/newfuweng.git /var/www/newfuweng
cd /var/www/newfuweng
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
npm ci
npm run build
```

生产服务器完成构建后不需要运行 Node.js。也可以在 CI 中执行 `npm ci && npm run build`，将 `public/build` 随发布包部署。

## 3. 环境配置

编辑 `.env`：

```dotenv
APP_NAME="幸运跳棋大冒险"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://activity.example.com
APP_TIMEZONE=Asia/Bangkok

DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

`APP_URL` 必须替换成最终域名。不要把 `.env` 或生产数据库提交到 Git。

## 4. 初始化数据库

首次演示部署：

```bash
php artisan migrate --force
php artisan db:seed --force
```

Seeder 会创建演示账号和活动数据。正式上线前应立即修改或删除演示账号。已有生产数据的升级部署只执行 `php artisan migrate --force`，绝不能执行 `migrate:fresh`。

设置权限：

```bash
sudo chown -R www-data:www-data storage bootstrap/cache database
sudo chmod -R ug+rwX storage bootstrap/cache database
```

## 5. SQLite 配置

确保数据库位于服务器本地磁盘。可执行：

```bash
sqlite3 database/database.sqlite 'PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;'
```

应用事务必须保持短小。支付接口、通知和钱包发放不能在数据库写事务中等待远程响应。

## 6. Nginx 配置

创建 `/etc/nginx/sites-available/newfuweng`：

```nginx
server {
    listen 80;
    server_name activity.example.com;
    root /var/www/newfuweng/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\. { deny all; }
}
```

启用站点：

```bash
sudo ln -s /etc/nginx/sites-available/newfuweng /etc/nginx/sites-enabled/newfuweng
sudo nginx -t
sudo systemctl reload nginx
```

建议通过 Certbot 配置 HTTPS：

```bash
sudo certbot --nginx -d activity.example.com
```

## 7. 定时任务和队列

Cron：

```cron
* * * * * cd /var/www/newfuweng && php artisan schedule:run >> /dev/null 2>&1
```

队列可通过 Supervisor 托管：

```ini
[program:newfuweng-worker]
command=php /var/www/newfuweng/artisan queue:work --sleep=3 --tries=3 --timeout=90
directory=/var/www/newfuweng
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
```

## 8. 部署后验证

```bash
php artisan about
php artisan migrate:status
php artisan config:cache
php artisan route:cache
php artisan view:cache
curl -I https://activity.example.com/login
```

浏览器验证登录、签到、跳棋、排行榜和管理后台充值记账。

升级到包含全量掷骰反馈的版本后，还应验证：正式地标显示紫色“地标”角标、电池显示金色奖励标识、普通落地与奖励落地均有结果弹框、音效开关可以持久保存。服务器必须先执行 `php artisan migrate --force`，否则走棋反馈字段尚未创建。

## 9. 访问地址

- 本地开发启动后：`http://127.0.0.1:8000`
- 局域网启动时：`http://服务器局域网IP:8000`
- Nginx 生产部署：`.env` 中 `APP_URL` 的地址，例如 `https://activity.example.com`
- 管理后台：在访问地址后增加 `/admin`，例如 `https://activity.example.com/admin`

仓库无法预先知道服务器公网 IP 或域名，因此真正的公网地址由部署时填写的 `APP_URL` 和 Nginx `server_name` 决定。

## 10. 更新、备份与回滚

更新前先备份：

```bash
cp database/database.sqlite "database/backup-$(date +%Y%m%d-%H%M%S).sqlite"
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

建议每天备份 SQLite 数据库并异地保存。回滚前停止写请求，恢复对应数据库备份和代码版本，避免代码结构与数据库结构不匹配。
