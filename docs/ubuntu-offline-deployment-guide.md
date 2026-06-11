# MeEdu 离线环境部署使用教程

> 适用于 Ubuntu 无公网访问、无法接收短信的离线环境。本节覆盖从在线机器制作镜像包、拷贝到离线机器部署、在后台创建**用户名+密码**用户、以及学员通过用户名登录的完整流程。

---

## 目录

1. [前置条件](#1-前置条件)
2. [在线环境：构建 Docker 镜像](#2-在线环境构建-docker-镜像)
3. [离线环境：传输并载入镜像](#3-离线环境传输并载入镜像)
4. [离线环境：配置与启动](#4-离线环境配置与启动)
5. [后台添加学员账号](#5-后台添加学员账号)
6. [学员登录](#6-学员登录)
7. [功能验证](#7-功能验证)
8. [常见问题](#8-常见问题)

---

## 1. 前置条件

### 离线环境（Ubuntu 服务器）

| 依赖项 | 最低版本 | 说明 |
|--------|----------|------|
| Docker | 20.10+ | `sudo apt install docker.io` |
| Docker Compose | v2.0+ | 建议使用 `docker compose` 插件 |
| 可用端口 | 8000 / 8100 / 8200 / 8300 | 分别对应 API / PC / H5 / Admin |

```bash
# 确认 Docker 可用
docker --version
docker compose version

# 确认目标端口未被占用
ss -tlnp | grep -E "8000|8100|8200|8300"
```

### 在线环境（需临时访问外网的机器）

- 能够访问 `registry.cn-hangzhou.aliyuncs.com`（阿里云容器镜像仓库）
- Docker 安装完成
- 已经 `git clone` 了 MeEdu 源码并切换到包含 `username` 字段的版本

---

## 2. 在线环境：构建 Docker 镜像

在能访问公网的机器上完成源码拉取和镜像构建。

### 2.1 获取源码

```bash
cd /opt
git clone https://github.com/xxx/meedu.git    # 替换为实际仓库地址
cd meedu

# 确保包含用户名登录相关的提交（2026/06/11 及之后的版本）
git log --oneline -5
#   e9822134 在无公网访问、无法接收短信的 Ubuntu 等离线环境中，MeEdu 支持**用户名+密码**方式登录，无需手机验证码
```

### 2.2 拉取基础镜像

```bash
# 从阿里云容器镜像仓库拉取基础镜像（需确保在线环境能访问该仓库）
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/php:7.4-fpm-alpine
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/node:20-alpine
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/mysql:8.1
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/redis:7.0.12
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/meilisearch:0.24.0
```

### 2.3 构建 MeEdu 应用镜像

```bash
cd /opt/meedu

# 构建镜像（前端编译 + PHP 依赖安装 + 生产配置缓存）
docker build --network=host -t meedu:offline -f Dockerfile .
```

> **说明**：构建过程会执行前端 `pnpm build`、PHP `composer install`、数据库迁移等操作。耗时约 3–8 分钟，取决于机器性能。

构建成功后确认镜像：

```bash
docker images | grep meedu
# meedu    offline    <IMAGE_ID>    <SIZE>
```

### 2.4 导出镜像为离线包

```bash
# 导出所有需要的镜像到一个 tar 包
docker save -o meedu-offline.tar \
  meedu:offline \
  registry.cn-hangzhou.aliyuncs.com/hzbs/mysql:8.1 \
  registry.cn-hangzhou.aliyuncs.com/hzbs/redis:7.0.12 \
  registry.cn-hangzhou.aliyuncs.com/hzbs/meilisearch:0.24.0

# 压缩以减小体积（可选，通常可减小 40%-60%）
gzip meedu-offline.tar
# 得到 meedu-offline.tar.gz

ls -lh meedu-offline.tar.gz
```

将 `meedu-offline.tar.gz` 拷贝到离线 Ubuntu 服务器（USB / 内网 U 盘 / 局域网 scp）。

---

## 3. 离线环境：传输并载入镜像

### 3.1 解压并载入

```bash
# 在离线 Ubuntu 服务器上
gunzip meedu-offline.tar.gz       # 如果已压缩
docker load -i meedu-offline.tar

# 确认镜像导入成功
docker images
# 应看到：
#   meedu:offline
#   registry.cn-hangzhou.aliyuncs.com/hzbs/mysql:8.1
#   registry.cn-hangzhou.aliyuncs.com/hzbs/redis:7.0.12
#   registry.cn-hangzhou.aliyuncs.com/hzbs/meilisearch:0.24.0
```

### 3.2 准备项目目录

```bash
mkdir -p /opt/meedu && cd /opt/meedu
```

---

## 4. 离线环境：配置与启动

### 4.1 创建 `compose.yml`

```bash
cat > /opt/meedu/compose.yml << 'COMPOSE_EOF'
x-logging: &default-logging
  driver: "json-file"
  options:
    max-size: "10m"
    max-file: "10"

networks:
  meedu-network:
    driver: bridge

volumes:
  data_mysql:
  data_redis:
  data_meilisearch:

services:
  meedu:
    image: meedu:offline
    restart: always
    environment:
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_DATABASE=meedu
      - DB_USERNAME=root
      - DB_PASSWORD=meeduxyz
      - REDIS_HOST=redis
      - REDIS_PASSWORD=${REDIS_PASSWORD:-F9nO2FzJ*%uDX58!}
      - REDIS_PORT=6379
      - QUEUE_DRIVER=sync
      - APP_KEY=${APP_KEY}
      - JWT_SECRET=${JWT_SECRET}
      - MEILISEARCH_HOST=http://meilisearch:7700
      - MEILISEARCH_KEY=
    ports:
      - "8000:8000"
      - "8100:8100"
      - "8200:8200"
      - "8300:8300"
    networks:
      - meedu-network
    depends_on:
      - mysql
      - redis
    logging: *default-logging

  redis:
    image: registry.cn-hangzhou.aliyuncs.com/hzbs/redis:7.0.12
    restart: always
    volumes:
      - data_redis:/data
    networks:
      - meedu-network
    logging: *default-logging

  mysql:
    image: registry.cn-hangzhou.aliyuncs.com/hzbs/mysql:8.1
    restart: always
    environment:
      - MYSQL_DATABASE=meedu
      - MYSQL_ROOT_PASSWORD=meeduxyz
    volumes:
      - data_mysql:/var/lib/mysql
    networks:
      - meedu-network
    logging: *default-logging

  meilisearch:
    image: registry.cn-hangzhou.aliyuncs.com/hzbs/meilisearch:0.24.0
    restart: always
    volumes:
      - data_meilisearch:/meili_data
    networks:
      - meedu-network
    logging: *default-logging
COMPOSE_EOF
```

### 4.2 创建 `.env` 配置文件

```bash
# 生成随机密钥（在离线 Ubuntu 服务器上执行）
APP_KEY_BASE64=$(head -c 32 /dev/urandom | base64 | tr -d '\n')
JWT_KEY_BASE64=$(head -c 32 /dev/urandom | base64 | tr -d '\n')

cat > /opt/meedu/.env << EOF
APP_KEY=base64:${APP_KEY_BASE64}
JWT_SECRET=${JWT_KEY_BASE64}
REDIS_PASSWORD=F9nO2FzJ*%uDX58!
EOF

# 查看生成的文件
cat /opt/meedu/.env
```

> **重要**：`APP_KEY` 和 `JWT_SECRET` 是安全关键配置，请勿使用示例值。上述脚本会在每台机器上生成不同的随机密钥。

### 4.3 启动服务

```bash
cd /opt/meedu
docker compose up -d
```

**启动过程（约 15–60 秒）：**

1. MySQL、Redis、Meilisearch 依次启动
2. `meedu` 容器等待 MySQL/Redis 就绪
3. 自动执行数据库迁移（创建含 `username` 字段的 `users` 表）
4. 同步系统配置和权限
5. 初始化默认管理员账号

### 4.4 确认服务就绪

```bash
# 查看容器状态（全部应为 Up / Running）
docker compose ps

# 查看 meedu 日志，确认启动完成
docker compose logs meedu | tail -10
```

正常日志输出应包含：

```
执行数据库迁移...
同步最新配置...
同步后台管理权限...
系统默认管理员已初始化!
NOTICE: fpm is running, pid 7
NOTICE: ready to handle connections
```

---

## 5. 后台添加学员账号

### 5.1 登录管理后台

1. 浏览器访问 `http://<离线服务器IP>:8300`
2. 使用默认管理员账号登录：

| 字段 | 值 |
|------|-----|
| 邮箱 | `meedu@meedu.meedu` |
| 密码 | `meedu123` |
| 图形验证码 | 按页面提示输入 |

> **安全提示**：首次登录后请立即修改默认管理员密码。

### 5.2 添加学员（用户名+密码）

1. 进入左侧菜单 **学员管理** → 点击 **添加学员**
2. 填写以下信息：

| 字段 | 必填 | 说明 |
|------|------|------|
| **用户名** | 否（建议填写） | 离线环境学员登录用，如 `zhangsan`、`stu001` |
| 手机号码 | **否（离线环境可留空）** | 不再强制填写 |
| **登录密码** | **是** | 学员登录密码，如 `123456` |
| 学员昵称 | 否 | 显示名称 |
| 学员头像 | 否 | 可上传 |

3. 点击 **确定** 完成创建

> **关键变化**：手机号码改为选填。离线环境下只需填写 **用户名** + **登录密码** 即可创建学员账号。

### 5.3 批量导入用户名

如果需要批量创建学员，可以使用导入功能：

1. **学员管理** → **导入学员**
2. 准备 CSV/Excel 文件，确保包含 `username`（用户名）列：

| username | mobile | nick_name | password |
|----------|--------|-----------|----------|
| stu001 | | 张三 | 123456 |
| stu002 | | 李四 | 123456 |

> **注意**：离线环境下 `mobile` 可留空，系统会自动为空的 `mobile` 生成一个默认值。

---

## 6. 学员登录

### 6.1 PC 网页端

1. 浏览器访问 `http://<离线服务器IP>:8100`
2. 点击 **登录** → 选择 **密码登录**
3. 输入**用户名**和**密码**，无需手机验证码
4. 点击登录即可进入学习

### 6.2 H5 移动端

1. 手机浏览器访问 `http://<离线服务器IP>:8200`
2. 点击 **登录** → **密码登录**
3. 输入**用户名**和**密码**
4. 登录成功

### 6.3 API 直接调用

```bash
# 用户名 + 密码登录
curl -X POST http://<离线服务器IP>:8000/api/v2/login/password \
  -H "Content-Type: application/json" \
  -d '{"username":"stu001","password":"123456"}'

# 成功响应示例
# {
#   "code": 0,
#   "message": "",
#   "data": {
#     "token": "eyJ0eXAi...（JWT令牌）"
#   }
# }

# 使用 Token 访问受保护的接口
curl http://<离线服务器IP>:8000/api/v2/member/detail \
  -H "Authorization: Bearer <上面返回的token>"
```

### 6.4 登录逻辑说明

```
请求登录 (username + password)
       │
       ▼
  username 是否提供？
      /          \
    是            否（仅 mobile）
     │               │
     ▼               ▼
 usernameLogin()   passwordLogin()
 按 username 或     仅按 mobile 匹配
 mobile 匹配用户
     │               │
     └──────┬────────┘
            ▼
    密码 Hash::check() 验证
            │
      ┌─────┴─────┐
      ▼             ▼
   匹配成功       匹配失败
   返回 JWT      返回 "账号或密码错误"
```

---

## 7. 功能验证

### 7.1 验证用户名列存在

```bash
docker compose exec mysql mysql -uroot -pmeeduxyz meedu \
  -e "DESCRIBE users;" | grep username
# 应输出: username  varchar(32)  YES  UNI  NULL
```

### 7.2 创建测试用户并验证登录

```bash
# 1. 生成密码哈希
HASH=$(docker compose exec meedu php -r "echo password_hash('test123456', PASSWORD_BCRYPT).PHP_EOL;")

# 2. 插入纯用户名测试用户（无手机号）
docker compose exec mysql mysql -uroot -pmeeduxyz meedu -e "
INSERT INTO users (username, mobile, avatar, nick_name, password, is_active, is_lock, is_password_set, register_ip, register_area)
VALUES ('testuser', '13800000001', '', '测试用户', '${HASH}', 1, 0, 1, '127.0.0.1', 'local');
"

# 3. 测试用户名登录
curl -s -X POST http://localhost:8000/api/v2/login/password \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"test123456"}'
# 应返回 code:0 和 JWT token
```

### 7.3 完整测试矩阵

| # | 场景 | 请求体 | 预期结果 |
|---|------|--------|----------|
| 1 | 纯用户名登录 | `{"username":"stu001","password":"123456"}` | `code:0` 返回 token |
| 2 | 用户名+密码（有手机号） | `{"username":"zhangsan","password":"123456"}` | `code:0` 返回 token |
| 3 | 手机号登录（兼容） | `{"mobile":"13800000001","password":"123456"}` | `code:0` 返回 token |
| 4 | 错误密码 | `{"username":"stu001","password":"wrong"}` | `code:1` "账号或密码错误" |
| 5 | 缺少必填字段 | `{"password":"123456"}` | 验证错误提示 |
| 6 | Token 访问受保护 API | `Authorization: Bearer <token>` | `code:0` 正常返回数据 |

---

## 8. 常见问题

### Q1: 容器启动后 meedu 无法连接 Redis？

**现象**：日志显示 `无法连接redis`

**解决**：确认 `.env` 中 `REDIS_PASSWORD` 与 Redis 镜像的 `requirepass` 一致。默认 Redis 密码为 `F9nO2FzJ*%uDX58!`（可在 Redis 配置中修改）。

```bash
# 验证 Redis 密码
docker compose exec redis cat /usr/local/etc/redis/redis.conf | grep requirepass
```

### Q2: 如何修改默认的 Redis 密码？

修改 Redis 配置文件或通过环境变量覆盖，然后重启。

### Q3: 端口被占用怎么办？

修改 `compose.yml` 中 `meedu` 服务的 `ports` 映射，如将 `8000:8000` 改为 `18000:8000`。

### Q4: 如何备份数据？

```bash
# 备份 MySQL 数据库
docker compose exec mysql mysqldump -uroot -pmeeduxyz meedu > meedu_backup_$(date +%Y%m%d).sql

# 备份 Docker volumes
docker run --rm -v meedu_data_mysql:/data -v $(pwd):/backup alpine tar czf /backup/mysql_vol_backup.tar.gz -C /data .
```

### Q5: docker compose up 后 MySQL 启动太慢导致 meedu 迁移失败？

`compose.yml` 中的 `CMD` 已内置 `sleep 15` 等待。如果仍然太慢，可手动重启 meedu 容器：

```bash
docker compose restart meedu
```

### Q6: 管理后台登录提示"图形验证码错误"？

检查 Redis 是否正常运行（图形验证码依赖 Redis 存储）：

```bash
docker compose exec redis redis-cli -a "F9nO2FzJ*%uDX58!" ping
# 应返回 PONG
```

### Q7: 需要在线环境，如何配置短信？

如需短信功能，在管理后台配置阿里云/腾讯云短信服务即可。本方案主要解决**无需短信**的离线部署场景。

---

## 附录：架构概览

```
┌─────────────────────────────────────────────────┐
│              离线 Ubuntu 服务器                    │
│                                                   │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐       │
│  │ :8300    │  │ :8100    │  │ :8200    │       │
│  │ Admin    │  │ PC 前端  │  │ H5 前端  │       │
│  │ (后台)   │  │          │  │          │       │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘       │
│       │             │             │              │
│       └──────────┬──┴─────────────┘              │
│                  │                               │
│           ┌──────┴──────┐                        │
│           │  :8000 API  │                        │
│           │  PHP 7.4    │                        │
│           └──────┬──────┘                        │
│                  │                               │
│       ┌──────────┼──────────┐                    │
│  ┌────┴────┐ ┌───┴───┐ ┌───┴────────┐          │
│  │ MySQL   │ │ Redis │ │ Meilisearch│          │
│  │ (用户)  │ │(缓存) │ │ (搜索)     │          │
│  └─────────┘ └───────┘ └────────────┘          │
└─────────────────────────────────────────────────┘
```

**核心表结构变化：**

```sql
-- users 表新增 username 字段
ALTER TABLE users ADD COLUMN username VARCHAR(32) NULL UNIQUE AFTER id;
```

**关键代码路径：**

| 功能 | 路径 |
|------|------|
| 登录控制器 | `app/Http/Controllers/Api/V2/LoginController.php` |
| 用户服务 | `app/Services/Member/Services/UserService.php` |
| 注册请求验证 | `app/Http/Requests/ApiV2/PasswordLoginRequest.php` |
| 后台用户管理 | `app/Http/Controllers/Backend/Api/V1/MemberController.php` |
| 数据库迁移 | `database/migrations/2026_06_11_000000_add_username_to_users_table.php` |
| 后台创建页面 | `xyz.meedu.admin/src/pages/member/components/create.tsx` |

---

> **版本**：v4.9.31+ | **更新日期**：2026/06/11 | **适用环境**：Ubuntu 20.04/22.04 离线部署
