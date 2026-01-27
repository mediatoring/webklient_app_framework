# WebklientApp Framework - Implementation Tasks

## Filozofie

Headless API-first framework: backend = RESTful JSON API, frontend = oddělený projekt.
První uživatel = developer (sudo) s kompletním audit logem.

---

## Fáze 1: Základní infrastruktura

- [ ] 1.1 Vytvořit projektovou strukturu adresářů (backend/, public/, core/, modules/, storage/, config/, frontend/)
- [ ] 1.2 Nastavit Composer a PSR-4 autoloading
- [ ] 1.3 Vytvořit .env soubor a ConfigLoader třídu
- [ ] 1.4 Implementovat Bootstrap třídu pro inicializaci aplikace
- [ ] 1.5 Vytvořit databázové schéma (MariaDB) - users, roles, permissions, api_tokens, activity_log, modules, files
- [ ] 1.6 Implementovat Database Connection třídu (PDO, connection pooling)
- [ ] 1.7 Implementovat QueryBuilder (fluent interface)
- [ ] 1.8 Vytvořit migration systém (timestamp-based, up/down)
- [ ] 1.9 Spustit počáteční migrations
- [ ] 1.10 Implementovat install script pro první setup

## Fáze 2: Router a HTTP vrstva

- [ ] 2.1 Implementovat Request třídu (parsing HTTP requestů)
- [ ] 2.2 Implementovat Response třídy (JsonResponse, ErrorResponse, CreatedResponse, NoContentResponse, PaginatedResponse)
- [ ] 2.3 Vytvořit Router třídu (GET, POST, PUT, PATCH, DELETE)
- [ ] 2.4 Implementovat Route třídu (definice jednotlivých routes)
- [ ] 2.5 Implementovat Middleware interface a chain execution
- [ ] 2.6 Vytvořit CorsMiddleware a JsonMiddleware
- [ ] 2.7 Implementovat route registration (config/routes.php)
- [ ] 2.8 Otestovat routing a middleware chain

## Fáze 3: Autentizace a JWT

- [ ] 3.1 Integrovat firebase/php-jwt nebo vlastní JWT knihovnu
- [ ] 3.2 Vytvořit JWTService (generování, validace tokenů)
- [ ] 3.3 Implementovat Auth třídu (login, logout, refresh flow)
- [ ] 3.4 Vytvořit AuthMiddleware (Bearer token extraction, validation)
- [ ] 3.5 Implementovat refresh token mechanismus (access 15min, refresh 30d)
- [ ] 3.6 Vytvořit API endpointy: /api/auth/login, /api/auth/logout, /api/auth/refresh
- [ ] 3.7 Implementovat ukládání tokenů do DB (api_tokens tabulka)
- [ ] 3.8 Otestovat authentication flow včetně refresh

## Fáze 4: Autorizace a Permission systém

- [ ] 4.1 Implementovat Permission třídu
- [ ] 4.2 Implementovat Role třídu
- [ ] 4.3 Vytvořit PermissionMiddleware (kontrola oprávnění per route)
- [ ] 4.4 Implementovat Permission Matrix loading (memory/cache)
- [ ] 4.5 Vytvořit API endpointy pro roles a permissions CRUD
- [ ] 4.6 Implementovat sudo roli (full access, nesmazatelná)
- [ ] 4.7 Vytvořit prvního sudo uživatele při instalaci
- [ ] 4.8 Otestovat permission checking

## Fáze 5: Activity Log systém

- [ ] 5.1 Vytvořit ActivityLog třídu
- [ ] 5.2 Implementovat ActivityLogMiddleware (automatické logování všech requestů)
- [ ] 5.3 Specialized logging pro critical actions (login, permission changes, config changes)
- [ ] 5.4 API endpointy: GET /api/activity-log, /api/activity-log/{id}, /api/activity-log/my, /api/activity-log/stats
- [ ] 5.5 Filtering a search (user_id, action_type, resource_type, date range)
- [ ] 5.6 Retention policy a archivace (90d default)
- [ ] 5.7 Cron job pro čištění a archivaci
- [ ] 5.8 Otestovat logging všech typů akcí

## Fáze 6: Modulární systém

- [ ] 6.1 Vytvořit ModuleInterface
- [ ] 6.2 Implementovat ModuleManager (loading, správa)
- [ ] 6.3 Implementovat ModuleRegistry (tracking installed modules)
- [ ] 6.4 API endpointy pro module management
- [ ] 6.5 Module install/uninstall flow
- [ ] 6.6 Vytvořit sample module jako template
- [ ] 6.7 Module permission registration
- [ ] 6.8 Otestovat module lifecycle

## Fáze 7: AI Services integrace

- [ ] 7.1 Implementovat OpenAIClient
- [ ] 7.2 Implementovat ClaudeAIClient (Anthropic API)
- [ ] 7.3 AIService jako unified interface (s fallback)
- [ ] 7.4 Caching AI responses
- [ ] 7.5 Conversation logging do DB
- [ ] 7.6 API endpointy: /api/ai/chat, /api/ai/conversations, /api/ai/usage, /api/ai/models
- [ ] 7.7 Streaming responses přes SSE (/api/ai/chat/stream)
- [ ] 7.8 Usage tracking a cost calculation
- [ ] 7.9 Otestovat oba providery a fallback

## Fáze 8: Validace a bezpečnost

- [ ] 8.1 Validator třídu (required, type, min/max, regex, enum, nested)
- [ ] 8.2 ValidationMiddleware
- [ ] 8.3 Sanitizer třídu (XSS, HTML encoding, trim, email normalize)
- [ ] 8.4 Hash třídu (bcrypt/Argon2id, password strength)
- [ ] 8.5 CSRF protection (double-submit cookie pro session-based)
- [ ] 8.6 Security headers middleware (X-Content-Type-Options, X-Frame-Options, CSP, HSTS)
- [ ] 8.7 Rate limiting systém (per user/IP, configurable per endpoint type)
- [ ] 8.8 IP blocking mechanismus (brute force protection)
- [ ] 8.9 Otestovat security measures

## Fáze 9: Cache systém

- [ ] 9.1 CacheInterface
- [ ] 9.2 FileCache driver
- [ ] 9.3 RedisCache driver (volitelně)
- [ ] 9.4 Cache tagging system
- [ ] 9.5 Cache invalidation logic (event-based)
- [ ] 9.6 Integrace do Permission systému
- [ ] 9.7 Integrace do AI services
- [ ] 9.8 Cache warming mechanism

## Fáze 10: File Upload a Storage

- [ ] 10.1 Storage interface (store, get, delete, exists, url)
- [ ] 10.2 LocalStorage driver
- [ ] 10.3 API endpoint POST /api/upload
- [ ] 10.4 File validation a sanitization (whitelist types, max size, UUID filename)
- [ ] 10.5 Files tabulka pro metadata
- [ ] 10.6 Thumbnail generation pro images
- [ ] 10.7 API endpoint GET /api/files/{id} s permission check
- [ ] 10.8 Otestovat upload a download flow

## Fáze 11: CRUD API endpointy

- [ ] 11.1 UsersController (CRUD, /api/users, /api/users/me, roles assignment)
- [ ] 11.2 RolesController (CRUD, permission management)
- [ ] 11.3 PermissionsController (matrix management)
- [ ] 11.4 ModulesController (module management)
- [ ] 11.5 ActivityLogController (filtering, search)
- [ ] 11.6 AIController (chat, usage)
- [ ] 11.7 Pagination pro všechny listing endpointy
- [ ] 11.8 Sorting a filtering
- [ ] 11.9 Otestovat všechny CRUD operace

## Fáze 12: API dokumentace

- [ ] 12.1 OpenAPI 3.0 specifikace (YAML)
- [ ] 12.2 Dokumentovat všechny endpointy s examples
- [ ] 12.3 Swagger UI na /api/docs
- [ ] 12.4 Code examples (JS, PHP, Python, cURL)
- [ ] 12.5 Authentication flow diagramy
- [ ] 12.6 Error responses a codes dokumentace
- [ ] 12.7 Postman collection
- [ ] 12.8 Review a finalizace

## Fáze 13: Testing

- [ ] 13.1 PHPUnit setup a test environment
- [ ] 13.2 Unit testy - Database třídy
- [ ] 13.3 Unit testy - Auth systém
- [ ] 13.4 Unit testy - Permission systém
- [ ] 13.5 Integration testy - API endpointy
- [ ] 13.6 Test authentication flow
- [ ] 13.7 Test module lifecycle
- [ ] 13.8 Test activity logging
- [ ] 13.9 Code coverage > 80%
- [ ] 13.10 Load testing scenarios (k6/JMeter)

## Fáze 14: Admin/Sudo features

- [ ] 14.1 Impersonation (POST /api/admin/impersonate, stop-impersonate)
- [ ] 14.2 Sudo-only API endpointy (/api/admin/*)
- [ ] 14.3 System info endpoint
- [ ] 14.4 Health check endpoint (DB, Redis, APIs, disk, memory)
- [ ] 14.5 Cache clear endpoint
- [ ] 14.6 Database optimization endpoint
- [ ] 14.7 Extended audit trail pro sudo (read-only)
- [ ] 14.8 Otestovat admin features

## Fáze 15: PWA features (backend)

- [ ] 15.1 ManifestGenerator třídu
- [ ] 15.2 Dynamic manifest endpoint
- [ ] 15.3 ServiceWorkerGenerator třídu
- [ ] 15.4 Dynamic service worker endpoint
- [ ] 15.5 PWA subscriptions tabulka
- [ ] 15.6 Web Push notifications API
- [ ] 15.7 Push subscription registration endpoint
- [ ] 15.8 Otestovat PWA features

## Fáze 16: Monitoring a Observability

- [ ] 16.1 Metrics collection (request count, response times, error rate, cache hit ratio)
- [ ] 16.2 Prometheus metrics endpoint
- [ ] 16.3 Sentry/error tracking integrace
- [ ] 16.4 Performance monitoring (APM)
- [ ] 16.5 Health check endpoint s details
- [ ] 16.6 Alerting pro critical issues
- [ ] 16.7 Monitoring dashboard
- [ ] 16.8 Otestovat monitoring

## Fáze 17: Deployment

- [ ] 17.1 Dockerfile (multi-stage build)
- [ ] 17.2 docker-compose.yml (development)
- [ ] 17.3 Production docker-compose
- [ ] 17.4 CI/CD pipeline (GitHub Actions)
- [ ] 17.5 Deployment scripts
- [ ] 17.6 Environment-specific configs
- [ ] 17.7 Rollback strategy
- [ ] 17.8 Otestovat deployment

## Fáze 18: Dokumentace finalizace

- [ ] 18.1 Developer dokumentace
- [ ] 18.2 Getting started guide
- [ ] 18.3 Deployment process docs
- [ ] 18.4 Troubleshooting guide
- [ ] 18.5 Security best practices
- [ ] 18.6 FAQ
- [ ] 18.7 README.md
- [ ] 18.8 Review

## Fáze 19: Production readiness

- [ ] 19.1 Security audit
- [ ] 19.2 Performance optimization
- [ ] 19.3 Load/stress testing
- [ ] 19.4 Database indexing optimization
- [ ] 19.5 Production monitoring setup
- [ ] 19.6 Backup strategy
- [ ] 19.7 Disaster recovery plan
- [ ] 19.8 Final review a sign-off

## Fáze 20: Frontend doporučení

- [ ] 20.1 Frontend architecture guidelines
- [ ] 20.2 API client implementation patterns
- [ ] 20.3 Authentication flow guide pro frontend
- [ ] 20.4 Error handling na frontend
- [ ] 20.5 UI/UX guidelines
- [ ] 20.6 PWA implementation docs
- [ ] 20.7 Sample frontend starter projekt
- [ ] 20.8 Review frontend dokumentace

---

## Priority

**Vysoká (jádro):** Fáze 1-5 — infrastruktura, routing, auth, permissions, activity log
**Střední (funkcionalita):** Fáze 6-11 — moduly, AI, security, cache, files, CRUD
**Nižší (kvalita):** Fáze 12-20 — docs, testing, admin, PWA, monitoring, deployment
