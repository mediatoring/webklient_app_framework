# WebklientApp Framework

Universal headless API-first application framework. Backend provides a RESTful JSON API, frontend is fully decoupled and can be any JavaScript framework (Vue, React, Svelte) or vanilla JS.

## Quick Start

### Prerequisites

- PHP 8.2+
- MariaDB 10.6+ / MySQL 8.0+
- Composer 2
- Docker & Docker Compose (optional, recommended)

### Using Docker (recommended)

```bash
# Clone framework into your new project
git clone https://github.com/mediatoring/webklient_app_framework.git my-app
cd my-app

# Start all services
docker compose up -d

# Install dependencies & run setup
docker compose exec api composer install
docker compose exec api php bin/install.php

# Seed development data (optional)
docker compose exec api php database/seeds/DevelopmentSeeder.php
```

API is available at `http://localhost:8080/api/health`

Docker Compose starts 4 services: PHP-FPM (`api`), Nginx (`nginx`, port 8080), MariaDB 11 (`db`, port 3306), Redis 7 (`redis`, port 6379).

### Manual Setup

```bash
cd backend
composer install
cp .env.example .env
# Edit .env with your database credentials
php bin/install.php
```

Serve with PHP built-in server:

```bash
php -S localhost:8080 -t public
```

## Creating a New App from This Framework

This framework is designed as a **starter template** for all your applications. Each new app gets its own copy of the framework codebase.

### Option 1: GitHub Template Repository (recommended)

Set this repo as a GitHub template (Settings > Template repository), then:

```bash
# Via GitHub CLI
gh repo create my-new-app --template mediatoring/webklient_app_framework --private

# Or click "Use this template" on the GitHub repo page
```

This creates a fresh repo with no commit history from the framework.

### Option 2: Clone and Reinitialize

```bash
git clone https://github.com/mediatoring/webklient_app_framework.git my-new-app
cd my-new-app
rm -rf .git
git init
git add .
git commit -m "Initial commit from WebklientApp Framework"

# Push to your new repo
git remote add origin git@github.com:mediatoring/my-new-app.git
git push -u origin main
```

### Option 3: Keep Framework as Upstream (for pulling updates)

If you want to receive framework updates in your app:

```bash
git clone https://github.com/mediatoring/webklient_app_framework.git my-new-app
cd my-new-app

# Rename origin to framework
git remote rename origin framework

# Add your app's repo as origin
git remote add origin git@github.com:mediatoring/my-new-app.git
git push -u origin main

# Later, pull framework updates:
git fetch framework
git merge framework/main --allow-unrelated-histories
```

### After Cloning

```bash
cd my-new-app/backend
composer install
cp .env.example .env
# Edit .env with your database credentials, API keys, etc.
php bin/install.php
```

The install script auto-generates `APP_KEY` and `JWT_SECRET`, runs all migrations, creates default roles (developer, admin, user) and permissions, assigns permissions to roles, and creates the sudo user `developer` with a random password printed to console.

## Project Structure

```
├── backend/
│   ├── bin/              # CLI scripts (install.php, migrate.php)
│   ├── config/           # Configuration files (routes, database, auth, cors, ai, etc.)
│   ├── core/             # Framework core
│   │   ├── AI/           # AI service clients (OpenAI, Anthropic)
│   │   ├── Auth/         # JWT authentication, AuthService, PermissionService
│   │   ├── Cache/        # Caching (FileCache, CacheInterface)
│   │   ├── Database/     # Connection, QueryBuilder, Migration, Migrator
│   │   ├── Exceptions/   # Exception hierarchy (App, Auth, Validation, RateLimit, NotFound)
│   │   ├── Http/         # Request, JsonResponse, Router, Route
│   │   │   └── Controllers/  # All API controllers
│   │   ├── Logging/      # ActivityLogger
│   │   ├── Middleware/    # Auth, CORS, RateLimit, Permission, Sudo, JSON, SecurityHeaders, ActivityLog
│   │   ├── Module/       # ModuleInterface, ModuleManager
│   │   ├── Security/     # Hash, RateLimiter, IpBlocker
│   │   ├── Storage/      # StorageInterface, LocalStorage
│   │   └── Validation/   # Validator, Sanitizer
│   ├── database/
│   │   ├── migrations/   # 9 database migrations
│   │   └── seeds/        # DevelopmentSeeder
│   ├── modules/          # Application modules (SampleModule included)
│   ├── public/           # Web root (index.php)
│   ├── storage/          # Logs, cache, uploads, archive (not in git)
│   └── tests/            # PHPUnit tests (Unit, System, Security, Integration)
├── docker/               # Nginx configuration
└── docker-compose.yml    # Docker Compose (api, nginx, db, redis)
```

## API Endpoints

### Health Check (public)
- `GET /api/health` — Status, timestamp, version

### Authentication
- `POST /api/auth/login` — Login, get JWT tokens
- `POST /api/auth/logout` — Invalidate tokens (auth required)
- `POST /api/auth/refresh` — Refresh access token

### Users (auth required)
- `GET /api/users/me` — Current user profile
- `PUT /api/users/me` — Update profile
- `PUT /api/users/me/password` — Change password
- `GET /api/users` — List users (`users.list`)
- `POST /api/users` — Create user (`users.create`)
- `GET /api/users/{id}` — Show user (`users.view`)
- `PUT /api/users/{id}` — Update user (`users.update`)
- `DELETE /api/users/{id}` — Deactivate user (`users.delete`)
- `GET /api/users/{id}/roles` — List user roles (`users.view`)
- `POST /api/users/{id}/roles` — Assign role (`users.update`)
- `DELETE /api/users/{id}/roles/{roleId}` — Remove role (`users.update`)

### Roles (auth required)
- `GET /api/roles` — List roles (`roles.list`)
- `POST /api/roles` — Create role (`roles.create`)
- `GET /api/roles/{id}` — Show role (`roles.view`)
- `PUT /api/roles/{id}` — Update role (`roles.update`)
- `DELETE /api/roles/{id}` — Delete role (`roles.delete`)
- `GET /api/roles/{id}/permissions` — List role permissions (`roles.view`)
- `POST /api/roles/{id}/permissions` — Assign permission (`roles.update`)
- `DELETE /api/roles/{id}/permissions/{permissionId}` — Remove permission (`roles.update`)

### Permissions (auth required)
- `GET /api/permissions` — List grouped by module (`permissions.list`)
- `GET /api/permissions/matrix` — Full permission matrix (`permissions.list`)
- `GET /api/permissions/check` — Check current user permission

### Modules (auth required)
- `GET /api/modules` — List all modules (`modules.list`)
- `GET /api/modules/{name}` — Module detail (`modules.view`)
- `POST /api/modules/{name}/enable` — Enable module (`modules.manage`)
- `POST /api/modules/{name}/disable` — Disable module (`modules.manage`)

### AI Services (auth required)
- `POST /api/ai/chat` — Send message, get response (`ai.chat`, rate limited)
- `POST /api/ai/chat/stream` — Streaming response via SSE (`ai.chat`, rate limited)
- `GET /api/ai/conversations` — List conversations (`ai.chat`)
- `GET /api/ai/conversations/{id}` — Conversation detail (`ai.chat`)
- `DELETE /api/ai/conversations/{id}` — Delete conversation (`ai.chat`)
- `GET /api/ai/usage` — Usage statistics (`ai.usage`)
- `GET /api/ai/models` — Available models (`ai.chat`)

### Activity Log (auth required)
- `GET /api/activity-log` — Browse with filters (`activity_log.list`)
- `GET /api/activity-log/my` — My activity
- `GET /api/activity-log/stats` — Statistics (`activity_log.stats`)
- `GET /api/activity-log/{id}` — Log detail (`activity_log.view`)

### Files (auth required)
- `POST /api/upload` — Upload file (`files.upload`)
- `GET /api/files/{id}` — Download file (`files.download`)

### Admin (sudo only)
- `GET /api/admin/system/info` — System information
- `GET /api/admin/system/health` — Health check
- `POST /api/admin/system/cache-clear` — Clear cache
- `POST /api/admin/system/optimize` — Optimize system
- `GET /api/admin/activity-log` — Browse all activity
- `GET /api/admin/users` — List all users
- `POST /api/admin/impersonate` — Impersonate user
- `POST /api/admin/stop-impersonate` — Stop impersonation
- `GET /api/admin/permissions/matrix` — Permission matrix
- `PUT /api/admin/permissions/matrix` — Bulk update permissions
- `POST /api/admin/permissions` — Create permission
- `PUT /api/admin/permissions/{id}` — Update permission
- `DELETE /api/admin/permissions/{id}` — Delete permission
- `POST /api/admin/modules/{name}/install` — Install module
- `DELETE /api/admin/modules/{name}` — Uninstall module

## Default Credentials

After `php bin/install.php`, the sudo user is created with a random password printed to console:
- **Username:** `developer`
- **Role:** developer (sudo)
- **Password:** Random, displayed once — save it immediately

Development seeder (`php database/seeds/DevelopmentSeeder.php`) creates additional users with password `password123`:
- `admin` — admin role (all permissions)
- `john` — user role
- `jane` — user role

## Module Development

Create a new module:

```
backend/modules/MyModule/
└── Module.php
```

Implement `WebklientApp\Core\Module\ModuleInterface` with methods: `getName()`, `getDisplayName()`, `getDescription()`, `getVersion()`, `getPermissions()`, `registerRoutes()`, `install()`, `uninstall()`, `enable()`, `disable()`.

See `modules/SampleModule/Module.php` for a complete template.

Install: `POST /api/admin/modules/MyModule/install`
Enable: `POST /api/modules/MyModule/enable`

## Database Migrations

The framework includes 9 migrations creating tables: `users`, `roles`, `user_roles`, `permissions`, `role_permissions`, `api_tokens`, `activity_log`, `archive_activity_log`, `modules`, `files`, `ai_conversations`, `ai_messages`, `rate_limits`, `ip_blocks`.

```bash
cd backend

# Run pending migrations
php bin/migrate.php

# Rollback last batch
php bin/migrate.php rollback

# Show migration status
php bin/migrate.php status
```

## Testing

```bash
cd backend
composer test
```

PHPUnit is configured with 4 test suites: **Unit**, **System**, **Security**, **Integration**. Tests are located in `backend/tests/`.

## Configuration

All configuration via `.env` file. Key settings:

| Variable | Description | Default |
|---|---|---|
| `APP_ENV` | `development` / `production` | `development` |
| `APP_DEBUG` | Enable debug mode | `true` |
| `APP_KEY` | Encryption key (auto-generated by installer) | |
| `JWT_SECRET` | JWT signing secret (auto-generated by installer) | |
| `JWT_ACCESS_TTL` | Access token TTL in seconds | `900` |
| `JWT_REFRESH_TTL` | Refresh token TTL in seconds | `2592000` (30 days) |
| `JWT_ALGO` | JWT algorithm | `HS256` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection | `127.0.0.1:3306` |
| `DB_CHARSET` | Database charset | `utf8mb4` |
| `CORS_ALLOWED_ORIGINS` | Allowed CORS origins | `*` |
| `RATE_LIMIT_PUBLIC` | Rate limit for public endpoints per window | `100` |
| `RATE_LIMIT_AUTHENTICATED` | Rate limit for auth endpoints per window | `1000` |
| `RATE_LIMIT_AI` | Rate limit for AI endpoints per window | `100` |
| `RATE_LIMIT_WINDOW` | Rate limit window in seconds | `3600` |
| `OPENAI_API_KEY` | OpenAI API key | |
| `OPENAI_DEFAULT_MODEL` | Default OpenAI model | `gpt-4` |
| `ANTHROPIC_API_KEY` | Anthropic API key | |
| `ANTHROPIC_DEFAULT_MODEL` | Default Anthropic model | `claude-sonnet-4-20250514` |
| `AI_DEFAULT_PROVIDER` | Default AI provider (`openai` / `anthropic`) | `openai` |
| `CACHE_DRIVER` | Cache driver (`file` / `redis`) | `file` |
| `REDIS_HOST`, `REDIS_PORT` | Redis connection | `127.0.0.1:6379` |
| `STORAGE_DRIVER` | Storage driver | `local` |
| `MAX_UPLOAD_SIZE` | Max upload size in bytes | `10485760` (10 MB) |
| `LOG_LEVEL` | Logging level | `debug` |
| `ACTIVITY_LOG_RETENTION_DAYS` | Activity log retention | `90` |
| `BCRYPT_ROUNDS` | Password hash cost | `12` |
| `LOGIN_MAX_ATTEMPTS` | Max login attempts before lockout | `5` |
| `LOGIN_LOCKOUT_MINUTES` | Lockout duration | `15` |
| `HSTS_ENABLED` | Enable HSTS header | `false` |

## License

MIT
