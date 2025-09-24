# Ndako API — Pro plan & step-by-step implementation (Laravel)

**Purpose:** A professional, modular, headless Laravel API for Ndako (property & hotel management). This document gives a clear, pragmatic, step-by-step plan: architecture, folder layout, conventions, example code, checklists and next actionable steps.

---

## High-level goals & principles

- **API-first, headless:** backend exposes a stable, versioned API consumed by web UI, mobile apps, and external integrators.
- **Modular & pluggable:** each feature is a module/package (Room Management, Reservations, Channel Manager, Payments, Accounting, Users, etc.) so teams can develop independently.
- **Domain-oriented & testable:** business logic lives in services/domain classes with thin controllers.
- **Secure & auditable:** tenancy support, RBAC, audit logs, rate limiting, secure webhooks.
- **Observability & CI/CD from day 1.**

---

## Non-functional requirements (short)

- API versioning (`/api/v1/...`) and forward-compatible evolution.
- JSON responses; adopt **JSON:API** shape or a lightweight RFC7807-style error envelope.
- High test coverage for domain logic and critical endpoints.
- Horizontal scale: stateless app servers, Redis-backed cache and queues.
- Work with S3-compatible object storage for media.

---

## Suggested technology stack

- **Laravel** (latest stable major release you prefer)
- **PHP 8.1+** (or the minimum supported by chosen Laravel version)
- Authentication: **Laravel Sanctum** (for SPA + mobile tokens). Consider OAuth2 (Passport) if you need third-party apps.
- RBAC: **spatie/laravel-permission**
- Multitenancy: lightweight approach (tenant_id on tables) or tenancy package (e.g. `stancl/tenancy`) depending on isolation needs.
- Queue: **Redis** + supervized workers (supervisord/systemd) or Octane if required.
- Search: **Meilisearch** or **Elasticsearch** (Meilisearch for fast dev iteration)
- Websockets (optional): **beyondcode/laravel-websockets**
- API docs: **Scribe** or OpenAPI generator
- Testing: **Pest** or PHPUnit + factories
- Local dev: Docker (sail or custom `docker-compose`)
- CI/CD: GitHub Actions / GitLab CI
- Observability: **Sentry** for errors, Prometheus/Grafana or hosted metrics

> Note: pick the tenancy approach early — changing it later is expensive.

---

## Architecture & module approach

**Monorepo with packages/modules** (recommended): keep everything in one repo, with each module under `packages/` or `app/Modules`.

Example modules:
- `Modules/User` (auth, profiles, RBAC)
- `Modules/Property` (properties, buildings, units)
- `Modules/Room` (rooms, room types, amenities)
- `Modules/Reservation` (reservations, availability, rates)
- `Modules/Payments` (payments, refunds, connectors)
- `Modules/ChannelManager` (integrations, sync jobs)
- `Modules/Accounting` (basic reporting, invoices)
- `Modules/Notifications` (email/SMS/push)

Each module contains:
- `Database` (migrations, factories, seeders)
- `Http` (Controllers, Requests)
- `Resources` (API Resources)
- `Models`
- `Services` / `Domain` logic
- `Events` / `Listeners`
- `Jobs` (queueable)
- `Routes` (module routes or a central api.php that loads module routes)
- `Tests`

**ModuleServiceProvider** registers module bindings, routes, event listeners.

---

## Folder structure (example)

```
/ (repo root)
├── app/
├── bootstrap/
├── config/
├── database/
├── packages/
│   └── Ndako/
│       ├── Property/
│       │   ├── src/
│       │   │   ├── Http/Controllers/Api/V1/PropertyController.php
│       │   │   ├── Models/Property.php
│       │   │   ├── Migrations/2025_01_01_create_properties_table.php
│       │   │   ├── Resources/PropertyResource.php
│       │   │   └── ServiceProvider.php
│       └── Reservation/
├── routes/
│   └── api.php        # loads modules routes and applies api/v1 prefix
├── tests/
└── docker-compose.yml
```

**Routing convention**: modules register routes under `api/v1/moduleslug/...` or central `routes/api.php` that composes them. Protect with `auth:sanctum` and `tenant` middleware where required.

---

## API conventions & contract

- **Base URL:** `/api/v1/`
- **Versioning:** Keep major version in path.
- **Response envelope:** Use consistent fields `{ data, meta, errors }` or JSON:API.
- **Date/time:** ISO 8601 in UTC. Keep clients responsible for locale formatting.
- **Pagination:** Cursor-based or page-based (default `per_page` param). Include `meta.pagination`.
- **Filtering & sorting:** Use query params, follow OData-like conventions or use `spatie/laravel-query-builder`.
- **Error handling:** Use standardized HTTP codes + structured JSON error payloads.

---

## Authentication & Authorization

- Use **Sanctum** for issuing API tokens to mobile apps and SPA. Support token abilities/scopes (e.g., `reservations:create`).
- Use **spatie/laravel-permission** for roles & permissions: `owner`, `manager`, `staff`, `tenant`, `guest`.
- Protect resource access with policies and a `TenantScope` that limits queries to tenant resources.

**Auth endpoints (examples):**
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login` → returns token
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/refresh` (if you implement refresh tokens)

---

## Multi-tenancy (strategies)

Two common models:

1. **Single DB, tenant_id scoped** (recommended to start): Add `tenant_id` (or `property_id`) on tenant-scoped entities and apply a global scope. Simpler to develop and cheaper to operate.
2. **Isolated DB per tenant**: Strong isolation, harder to operate and migrate; use only if required.

Implementation notes:
- Implement a `TenantMiddleware` that resolves tenant from subdomain / header / JWT claim and sets `currentTenant()` globally.
- Use Global Eloquent scope (e.g., `TenantScoped`) to auto-apply `where tenant_id = X` to models.

---

## Database schema (high-level)

Tables you’ll need early:
- `users` (+ profiles)
- `tenants` / `organizations`
- `properties` (belongs to tenant)
- `buildings` (optional)
- `units` or `rooms` (room types, capacity, amenities)
- `reservations` (status, price, payer_id, unit_id, dates)
- `rates` or `pricing_rules`
- `payments` (transaction id, provider, status)
- `channels` (external connections)
- `audit_logs`

Design tips:
- Use `status` enums as strings/integers; consider Laravel's Enum casts.
- Store money in cents (integer) and currency separately.
- Use `uuid` for public-facing ids while keeping autoincrement for internal PK if needed.

---

## Availability & Pricing engine (design guidance)

- Externalize availability logic into a service/class (e.g., `AvailabilityService`) that accepts date ranges, rooms, and returns availability and breakdown by rate.
- Use cache for availability queries and invalidate cache on reservation create/cancel.
- Consider a `RatePlan` model and rules engine for seasonal/OTAs pricing.

---

## Events, Jobs & Integrations

- Emit domain events: `ReservationCreated`, `ReservationCancelled`, `PaymentSucceeded`.
- Listeners: `SendConfirmationEmail`, `SyncToChannelManager`, `UpdateAvailabilityCache`.
- All heavy work in queueable jobs.
- Webhooks: sign & timestamp webhooks, retry logic.

---

## Background processing & workers

- Use Redis queues, separate queues for `default`, `emails`, `channels`, `high_priority`.
- Workers: `php artisan queue:work --queue=high_priority,default --sleep=3 --tries=3` managed by supervisor.
- Use rate limiting for outgoing API calls to channel partners.

---

## Caching & performance

- Cache availability, property lists, and repeated expensive queries.
- Use Redis with proper TTL and key versioning (`availability:property:{id}:v1:{dateRangeHash}`).
- Use database indexes for date range queries (`start_date`, `end_date`, `unit_id`).

---

## Search

- Use Meilisearch for fast text search on properties and rooms. Sync models with `laravel/scout`.

---

## Storage & Media

- Use S3-compatible storage for images. Generate signed URLs for uploads (direct to S3) and transformations.

---

## Webhooks & External API patterns

- Provide endpoints for channels to call (signed payloads + replay protection).
- Provide admin UI to configure channel credentials and callbacks.

---

## Testing strategy

- **Unit tests** for services and domain logic.
- **Feature tests** for endpoints (happy path, auth, edge cases).
- **Integration tests** for queue jobs and external API integration (use mocking/stubbing).
- Use **factories** and clear test seeds; prefer in-memory DB or dedicated test DB.
- Add contract tests for external providers if needed.

---

## Documentation & API spec

- Use automated OpenAPI generation (Scribe or friends). Publish docs and keep them part of CI (fail if docs out of date).
- Provide Postman / Insomnia collection for external integrators.

---

## CI/CD & developer workflow (high level)

**CI pipeline** (example steps):
1. `composer install` + static analysis (PHPStan)
2. `phpunit` / `pest` tests
3. `php-cs-fixer` or `larastan` checks
4. Build Docker image (optional)
5. If on `main` and tests pass, deploy to staging/production via action

**Local dev**: docker-compose with services (php-fpm, nginx, mysql/postgres, redis, meilisearch)

---

## Observability & monitoring

- Integrate Sentry for exceptions.
- Centralized logs (ELK, LogDNA) or cloud alternative.
- Health checks and readiness probes for container orchestration.

---

## Security checklist (must-haves)

- TLS everywhere.
- Secrets in vault / CI secrets.
- Protect webhooks with signatures.
- Rate limit public endpoints.
- Input validation & sanitization.
- Audit logs on critical actions.

---

## Example: minimal module scaffolding (Property)

**Migration (simplified):**

```php
Schema::create('properties', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('tenant_id')->constrained('tenants');
    $table->string('name');
    $table->string('slug')->index();
    $table->text('description')->nullable();
    $table->json('meta')->nullable();
    $table->timestamps();
});
```

**Resource:**

```php
class PropertyResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'meta' => $this->meta ?? new stdClass(),
        ];
    }
}
```

**Controller (thin):**

```php
public function index(Request $request)
{
    $properties = Property::query()
        ->where('tenant_id', currentTenantId())
        ->paginate($request->get('per_page', 15));

    return PropertyResource::collection($properties);
}
```

---

## Acceptance checklist per-phase (samples)

**Phase: Core scaffolding & Auth**
- [ ] Repo + monorepo structure created
- [ ] Docker dev environment
- [ ] Sanctum auth + register/login endpoints
- [ ] RBAC package installed and seeded roles
- [ ] Tenant resolver & middleware
- [ ] Basic CI (lint + tests)

**Phase: Property & Rooms**
- [ ] Migrations + models + factories
- [ ] CRUD endpoints + Resources + Requests
- [ ] Basic search (scout + meilisearch)
- [ ] Tests for endpoints

**Phase: Reservations**
- [ ] Reservation model + availability service
- [ ] Reservation create flow (events, jobs)
- [ ] Unit & feature tests

---

## Suggested next actionable steps (pick one)

1. **Scaffold repo + Docker + CI** (baseline) — recommended first.
2. **Implement Auth + Tenant middleware + RBAC** — then seed users and tenants.
3. **Scaffold Property module** (migration, model, controller, tests).

---

## Final notes / opinions

- Start small: core domain (tenant, users, properties, units, reservations). Ship an internal API that your frontend/mobile can consume ASAP.
- Make modules independent but share a small set of core packages (auth, tenancy, events).
- Keep configuration data-driven: rates, tax rules, channel credentials all stored and editable.


---

**If you want, I can now:**
- Generate the Laravel repo scaffold (composer.json, Dockerfile, docker-compose, basic GitHub Actions), or
- Generate the full Auth + Tenant module code (models, migration, requests, controllers, resources, tests), or
- Scaffold the Property module complete with migrations and API tests.

Tell me which of the three I should generate right away and I will produce the code and files for you.

