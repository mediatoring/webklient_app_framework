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

The install script auto-generates `APP_KEY` and `JWT_SECRET`, runs all migrations, creates default roles/permissions, and creates the sudo user with a random password.

## Project Structure

```
backend/
├── bin/              # CLI scripts (install, migrate)
├── config/           # Configuration files (routes, database, auth, etc.)
├── core/             # Framework core
│   ├── AI/           # AI service clients (OpenAI, Anthropic)
│   ├── Auth/         # JWT authentication & controllers
│   ├── Cache/        # Caching (file, Redis)
│   ├── Database/     # Connection, QueryBuilder, Migrator
│   ├── Exceptions/   # Exception hierarchy
│   ├── Http/         # Request, Response, Router, Controllers
│   ├── Logging/      # Activity logger
│   ├── Middleware/    # Auth, CORS, RateLimit, Permissions, etc.
│   ├── Module/       # Module system
│   ├── Security/     # Hash, RateLimiter, IpBlocker
│   ├── Storage/      # File storage abstraction
│   └── Validation/   # Validator & Sanitizer
├── database/
│   ├── migrations/   # Database schema migrations
│   └── seeds/        # Development data seeders
├── modules/          # Application modules
├── public/           # Web root (index.php only)
├── storage/          # Logs, cache, uploads (not in git)
└── tests/            # PHPUnit tests
docker/               # Docker/nginx configs
frontend/             # Frontend project (separate)
```

## API Endpoints

### Authentication
- `POST /api/auth/login` - Login, get JWT tokens
- `POST /api/auth/logout` - Invalidate tokens
- `POST /api/auth/refresh` - Refresh access token

### Users
- `GET /api/users/me` - Current user profile
- `PUT /api/users/me` - Update profile
- `GET|POST /api/users` - List / Create
- `GET|PUT|DELETE /api/users/{id}` - Show / Update / Deactivate

### Roles & Permissions
- `GET|POST /api/roles` - List / Create
- `GET /api/permissions` - List grouped by module
- `GET /api/permissions/matrix` - Full permission matrix
- `GET /api/permissions/check?permission=slug` - Check permission

### Modules
- `GET /api/modules` - List all modules
- `POST /api/modules/{name}/enable` - Enable module
- `POST /api/modules/{name}/disable` - Disable module

### AI Services
- `POST /api/ai/chat` - Send message, get response
- `POST /api/ai/chat/stream` - Streaming response (SSE)
- `GET /api/ai/conversations` - Conversation history
- `GET /api/ai/models` - Available models

### Activity Log
- `GET /api/activity-log` - Browse with filters
- `GET /api/activity-log/my` - My activity
- `GET /api/activity-log/stats` - Statistics

### Admin (sudo only)
- `GET /api/admin/system/info` - System information
- `GET /api/admin/system/health` - Health check
- `POST /api/admin/impersonate` - Impersonate user
- `PUT /api/admin/permissions/matrix` - Bulk update permissions

### Files
- `POST /api/upload` - Upload file
- `GET /api/files/{id}` - Download file

## Default Credentials

After `php bin/install.php`, the sudo user is created with a random password printed to console. Save it.

Development seeder creates users with password `password123`:
- `developer` - sudo role (created by installer)
- `admin` - admin role
- `john`, `jane` - user role

## Module Development

Create a new module:

```
backend/modules/MyModule/
└── Module.php
```

Implement `WebklientApp\Core\Module\ModuleInterface`. See `modules/SampleModule` for a complete example.

Install: `POST /api/admin/modules/MyModule/install`
Enable: `POST /api/modules/MyModule/enable`

## Testing

```bash
cd backend
composer test
```

## Configuration

All configuration via `.env` file. Key settings:

| Variable | Description |
|---|---|
| `APP_ENV` | `development` / `production` |
| `APP_KEY` | Encryption key (auto-generated) |
| `JWT_SECRET` | JWT signing secret (auto-generated) |
| `JWT_ACCESS_TTL` | Access token TTL in seconds (default: 900) |
| `DB_*` | Database connection |
| `OPENAI_API_KEY` | OpenAI API key |
| `ANTHROPIC_API_KEY` | Anthropic API key |
| `RATE_LIMIT_*` | Rate limiting per endpoint type |

## License

MIT
