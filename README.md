<p align="center"><a href="https://www.sslbear.com?from=meedu">SSLBear - 云服务域名证书守护者|7x24小时监护让域名证书永不过期</a></p>

<h1 align="center">MeEdu - 数据安全的网校系统</h1>

<h4 align="center">
  <a href="https://www.meedu.vip">官网</a> |
  <a href="https://meedu.vip/price.html">商业版</a> |
  <a href="https://faq.meedu.vip">文档中心</a>
</h4>

<p align="center">⚡ 基于 PHP+Laravel 开发的在线网校解决方案 🔍</p>

**MeEdu** 是一款基于 PHP7.4 + Laravel8 + MySQL + Redis 开发的开源网校(知识付费)解决方案。支持线上点播、课程购买、网校装修、学员手机号/用户名密码登录注册、学习统计、角色管理等丰富功能。
**MeEdu** 是前后端分离的架构，支持 PC,H5 端口。此为 MeEdu 开源版本。**与此同时，我们还提供商业版本解决方案。商业版本支持直播课、考试练习、电子书、图文、站内问答、秒杀、团购、兑换码等更多功能；在开源的基础上还支持微信小程序、安卓 APP、苹果 APP 端口。**

## 🚀 快速上手

拉取代码：

```
git clone --branch main https://gitee.com/myteng/MeEdu.git meedu
```

运行(分 3 步):

**① 进入目录并复制环境配置**

```
cd meedu
cp .env.example .env          # Windows: 改为 copy .env.example .env
```

**② 编辑 `.env`,把 `APP_KEY=` 和 `JWT_SECRET=` 两行都填上随机密钥**

> `APP_KEY` 是 Laravel 全应用对称加密密钥(Cookie/Session/加密字段等);`JWT_SECRET` 是 JWT 签名密钥。两者**都必须自行生成且保密**,留空或使用公开示例值会导致 Cookie 可被解密、Token 可被伪造,出现未授权访问风险。

**生成 `APP_KEY`**(任选其一,必须是 `base64:<32 字节 base64>` 格式):

```
# macOS / Linux
echo "base64:$(openssl rand -base64 32)"

# Windows PowerShell
$b=New-Object byte[] 32;[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b);"base64:"+[Convert]::ToBase64String($b)
```

**生成 `JWT_SECRET`**(任选其一):

```
# macOS / Linux
openssl rand -base64 48

# Windows PowerShell
$b=New-Object byte[] 48;[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b);[Convert]::ToBase64String($b)
```

将输出分别粘贴到 `.env` 中对应行后面(等号后无空格),例如:

```
APP_KEY=base64:7tQp...(你生成的字符串)
JWT_SECRET=hVZ8b2pK...(你生成的字符串)
```

**③ 启动容器**

```
docker-compose up -d
```

> 🚨请注意，上述命令运行 MeEdu 存在一定的使用安全风险，仅供测试使用！如需在正式生产环境使用 MeEdu 还请阅读 [部署文档](https://faq.meedu.vip/doc/g9jK0KXmFe) 。

等待 `30s` 左右。现在打开您的浏览器，输入 `http://localhost:8300` 即可访问后台管理界面，默认管理员账号和密码 `meedu@meedu.meedu / meedu123` 。

- PC 端口 `http://localhost:8100`
- H5 端口 `http://localhost:8200`
- API 端口 `http://localhost:8000`

## 🔌 离线环境部署

在无公网访问、无法接收短信的 Ubuntu 等离线环境中，MeEdu 支持**用户名+密码**方式登录，无需手机验证码。

> 📖 完整离线部署教程请参阅：[docs/ubuntu-offline-deployment-guide.md](docs/ubuntu-offline-deployment-guide.md)

### 适用场景

- 内网隔离环境，服务器无法访问公网
- 无法接收手机短信验证码
- 企业内部培训、学校机房等离线教学场景

### 快速部署（4 步）

#### ① 准备镜像包（在能访问公网的机器上）

```bash
# 拉取基础镜像
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/php:7.4-fpm-alpine
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/node:20-alpine
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/mysql:8.1
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/redis:7.0.12
docker pull registry.cn-hangzhou.aliyuncs.com/hzbs/meilisearch:0.24.0

# 构建 MeEdu 镜像（含前端编译 + PHP 依赖）
cd meedu
docker build --network=host -t meedu:offline -f Dockerfile .

# 导出离线包
docker save -o meedu-offline.tar \
  meedu:offline \
  registry.cn-hangzhou.aliyuncs.com/hzbs/mysql:8.1 \
  registry.cn-hangzhou.aliyuncs.com/hzbs/redis:7.0.12 \
  registry.cn-hangzhou.aliyuncs.com/hzbs/meilisearch:0.24.0

gzip meedu-offline.tar
# 将 meedu-offline.tar.gz 拷贝到离线服务器
```

#### ② 在离线 Ubuntu 服务器上载入镜像

```bash
gunzip meedu-offline.tar.gz
docker load -i meedu-offline.tar
docker images  # 确认所有镜像导入成功
```

#### ③ 配置环境变量并启动

```bash
cd /opt/meedu

# 生成随机密钥
echo "APP_KEY=base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')" > .env
echo "JWT_SECRET=$(head -c 32 /dev/urandom | base64 | tr -d '\n')" >> .env
echo "REDIS_PASSWORD=F9nO2FzJ*%uDX58!" >> .env

# 启动所有服务
docker compose up -d
```

> ⚠️ 上述 `compose.yml` 中的 `meedu` 服务 `image` 需改为 `meedu:offline`，或创建 `docker-compose.override.yml` 覆盖。

#### ④ 验证服务状态

```bash
docker compose ps           # 所有容器应为 Up 状态
docker compose logs meedu | tail -5
# 应看到：系统默认管理员已初始化! ... ready to handle connections
```

### 后台管理

- 访问 `http://<服务器IP>:8300`，默认管理员 `meedu@meedu.meedu` / `meedu123`
- 进入 **学员管理** → **添加学员**：填写 **用户名** + **登录密码**，手机号可留空
- 支持 CSV/Excel 批量导入学员（username 列为用户名）

### 学员登录

- **PC 端**（`:8100`）：登录 → 密码登录 → 输入**用户名**和**密码**
- **H5 端**（`:8200`）：登录 → 使用密码登录 → 输入**用户名**和**密码**
- **API 端点**：`POST /api/v2/login/password`，请求体 `{"username":"用户名","password":"密码"}`

### 测试验证

```bash
# 用户名+密码登录
curl -s -X POST http://localhost:8000/api/v2/login/password \
  -H "Content-Type: application/json" \
  -d '{"username":"your_username","password":"your_password"}'
# 成功返回: {"code":0,"message":"","data":{"token":"eyJ0eX..."}}

# 手机号+密码登录（仍支持，兼容旧版）
curl -s -X POST http://localhost:8000/api/v2/login/password \
  -H "Content-Type: application/json" \
  -d '{"mobile":"13800000001","password":"your_password"}'
```

> 💡 在线环境仍可正常使用手机号+短信验证码登录，两种方式并存。

## 🔰️ 软件安全

安全问题应该通过邮件私下报告给 tengyongzhi@meedu.vip。 您将在 24 小时内收到回复，如果因为某些原因您没有收到回复，请通过回复原始邮件的方式跟进，以确保我们收到了您的原始邮件。

## 📃 使用许可

- 2024 © 杭州白书科技有限公司。
- 本软件遵循 Apache 2.0 许可证，附加特定的商业使用条件，使用此软件还需要遵循[附件条款和条件](ADDITIONAL_TERMS.md)。
