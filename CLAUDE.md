# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MeEdu is an open-source online education (知识付费) platform — PHP 7.4 + Laravel 8 API backend with three React 18 + TypeScript + Vite frontends (Admin, PC, H5). The repository is a monorepo with four sub-projects.

## Repository Layout

```
xyz.meedu.api/       # Laravel 8 API backend (PHP)
xyz.meedu.admin/     # Admin dashboard SPA (React + Ant Design + Vite, dev port 8300)
xyz.meedu.pc/        # PC-facing SPA (React + Ant Design + Vite, dev port 8100)
xyz.meedu.h5/        # Mobile H5 SPA (React + Ant Design Mobile + Vite, dev port 8200)
compose.yml          # Docker Compose: meedu app + MySQL + Redis + Meilisearch
```

## Development Commands

### Backend (`xyz.meedu.api/`)

```bash
# Install dependencies
composer install

# Run all tests
composer test
# Run a single test suite
./vendor/bin/phpunit --testsuite=Services
# Run a single test file
./vendor/bin/phpunit tests/Services/Course/CourseServiceTest.php
# Run a single test method
./vendor/bin/phpunit --filter testMethodName tests/Services/Course/CourseServiceTest.php

# Code style fix
./vendor/bin/php-cs-fixer fix

# Clear caches
composer clean       # route:clear + config:clear + view:clear
composer rebuild     # clear then re-cache routes/config/views

# Generate app key / JWT secret
php artisan key:generate
php artisan jwt:secret
```

### Frontends (`xyz.meedu.admin/`, `xyz.meedu.pc/`, `xyz.meedu.h5/`)

All three use **pnpm** and have identical scripts:
```bash
pnpm install
pnpm dev        # Start dev server
pnpm build      # TypeScript check + Vite production build
pnpm preview    # Preview production build
```

### Docker (from repo root)

```bash
docker-compose up -d    # Starts API (port 8000) + PC (8100) + H5 (8200) + Admin (8300)
```

## Backend Architecture

### Service-Oriented Design

The API follows a strict service-layer architecture. The five service domains live in `app/Services/`:

| Service | Directory | Responsibility |
|---------|-----------|----------------|
| `Base` | `app/Services/Base/` | System-level: config, cache (callable by all other services) |
| `Member` | `app/Services/Member/` | User registration, login, profile, VIP |
| `Course` | `app/Services/Course/` | Courses, videos/chapters, categories |
| `Order` | `app/Services/Order/` | Purchases, payments |
| `Other` | `app/Services/Other/` | Uploads, SMS, misc utilities |

**Critical rules** (from `app/Services/README.md`):
1. **Only `Base` services can be called by other services.** All other services must NOT call each other cross-domain.
2. Services within the same domain CAN call each other.
3. **All service methods must return basic data types** (arrays, booleans, strings, integers) — NEVER Model objects, Collection objects, or any ORM-bound types. This is for interoperability and testability.
4. All services are used via **dependency injection** (interfaces in `Services/*/Interfaces/`).
5. Only `Auth::id()` and `Auth::check()` are allowed globally — **never** use `Auth::user()` directly, as it returns a Model object.

### Authentication

Two JWT guards (`config/auth.php`):
- **`administrator`** — backend admin users (model: `App\Models\Administrator`)
- **`apiv2`** — frontend users/students (model: `App\Services\Member\Models\User`)

### Route Files

| File | Purpose |
|------|---------|
| `routes/backend-v2.php` | Admin API (auth: `administrator`, permission middleware `mbp`) |
| `routes/frontend-v3.php` | Public + authenticated user API (auth: `apiv2`) |
| `routes/backend-v1.php`, `routes/frontend.php`, `routes/frontend-v2.php` | Legacy/deprecated API versions |

### Controllers

```
app/Http/Controllers/
  Backend/   # Admin API controllers (organized by feature subdirectories)
  Frontend/  # User-facing API controllers
  Api/       # Legacy v1/v2 controllers
```

`BaseController` extends the standard Controller and injects `ConfigServiceInterface` for config access.

### Key Subsystems

- **`app/Meedu/`** — Core framework: addon system, caching, payment integration, SMS providers (Alibaba/Tencent Cloud), video/VOD providers, hooks, settings
- **`app/Hooks/`** — Plugin-like hook points: `CommentStoreCheck`, `OrderStore`, `ViewBlock`
- **`addons/`** — Addon/plugin directory (PSR-4 namespace `Addons\\`)
- **`config/meedu.php`** — Central configuration for members, upload, payments, system settings, WeChat MP, SMS, etc. Runtime config is stored via `ConfigServiceInterface` and can be modified through the admin panel.
- **`app/Businesses/`** — Business logic layer between controllers and services

### Testing

- Framework: PHPUnit 9
- Tests in `tests/` organized as: `Commands/`, `Services/`, `Api/`, `Unit/`
- Test classes extend `Tests\TestCase` (uses `CreatesApplication` trait, sets up testing DB)
- `phpunit.xml` sets `APP_ENV=testing` with array/sync drivers
- Service tests are the primary test type — test each service domain in isolation

### Coding Standards

PHP-CS-Fixer with PSR-2 + custom rules (short array syntax, ordered imports by length, no unused imports, single quotes). Excludes `addons/`, `public/`, `resources/`, `storage/`, `vendor/`, `bootstrap/`.

The license header required on all PHP files:
```php
/*
 * This file is part of the MeEdu.
 *
 * (c) 杭州白书科技有限公司
 */
```

## Frontend Architecture

All three frontends share the same tech stack patterns:
- **State management**: Redux Toolkit (`src/store/`)
- **Routing**: React Router v6, lazy-loaded pages (`src/routes/`)
- **HTTP**: axios (`src/api/`)
- **Auth**: JWT token stored in localStorage, managed via Redux
- **Build**: Vite with `@vitejs/plugin-react-swc` (SWC for fast compilation)

Each frontend has this src structure:
```
src/
  api/         # API request functions
  components/  # Shared components
  pages/       # Page components (feature-organized)
  routes/      # Route definitions with lazy loading
  store/       # Redux Toolkit slices
  utils/       # Utility functions
  types/       # TypeScript type definitions
```

## Infrastructure

- **Container images**: published to `registry.cn-hangzhou.aliyuncs.com/meedu/light`
- **Meilisearch**: v0.24.0 for full-text search (scout driver)
- **Queue**: sync by default; set `QUEUE_DRIVER=redis` to enable Redis queues
- **PHP extensions required**: curl, json, zip, openssl
