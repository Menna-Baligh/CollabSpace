# CLAUDE.md — CollabSpace Global Coding Constitution

This file governs **HOW** code is written, architected, tested, and documented in this repository. It contains no feature-specific business rules, roles/permissions, schemas, or endpoints. Those come from individual **Feature Implementation Prompts**. When behavior depends on domain rules, follow the active Feature Prompt and never invent global assumptions.

**Do not modify this file unless explicitly requested.**

---

## 1. Project Context & Engineering Goal

- **Project:** CollabSpace — backend RESTful API served under `/api/v1`.
- **Stack:** PHP 8.3+, Laravel 13, MySQL, JSON API, Pest, Laravel Pint.
- **Quality bar:** Production-grade code judged on clean architecture (SOLID), database design and performance, strict authorization and security, automated testing, and API/Postman documentation.
- **Verify before assuming:** Do not assume a listed tool or convention is installed or configured. Check `composer.json`, `composer.lock`, config files, and existing code first. If existing conventions differ from this file, **document the discrepancy and recommend an approach**. Never silently overwrite existing configuration.

## 2. Development Philosophy: Vertical Slices & Just-in-Time

- Build **one complete feature end-to-end at a time**. Do not scaffold the whole database or install all packages upfront.
- **Just-in-time packages:** install a Composer package only when the current slice requires it.
- **Never dump all code at once.** Follow this workflow:
  1. **Analyze:** restate the feature's rules, actors, and edge cases. Ask only high-impact clarifying questions.
  2. **Design:** propose the slice's schema/indexes, authorization rules, and API contract. **Wait for confirmation.**
  3. **Build step by step:**
     `Package (if needed) → Migration & Indexes → Enums & Models → Form Requests & Policies → Service/Action → Controller & Routes → API Resource → Pest Tests → Postman Collection JSON`
- **Before each step:** state the exact file path, its single responsibility, and whether it is new or modified.
- **After each step:** explain why it was written that way and what to verify.

## 3. Architecture & Responsibility Separation

Layers: Routes → Middleware → Controllers → Form Requests → Services/Actions → Models → API Resources. This is a guideline, not a mandatory sequence. Skip layers for simple operations.

- **Controllers:** HTTP coordination only. No business logic, no complex queries.
- **Form Requests:** all input validation. Use `validated()` or `safe()`. **Never `$request->all()`.**
- **Services/Actions:** hold business workflows when justified, kept cohesive.
- **Models:** persistence, relationships, casts, reusable query scopes. No complex business workflows.
- **Do not mechanically introduce** Repositories, DTOs, Interfaces, Abstract Classes, Traits, Events, or Queued Jobs. Add them only for real complexity or a clear architectural need.
- Keep functions focused and classes cohesive. Use reasonable size guidelines, not arbitrary hard limits.
- Use explicit PHP 8.3+ types (parameters, returns, properties) and **PHP Enums** for fixed states.

## 4. Laravel 13 & Eloquent Conventions

- Use modern Laravel 13 structure: API routes in `routes/api.php`; routing, middleware, and exception handling configured in `bootstrap/app.php`. **Do not** rely on `Kernel.php` or `RouteServiceProvider`.
- Prefer **Dependency Injection** over `app()` or static facades inside domain services when DI is cleaner and more testable.
- In `AppServiceProvider::boot()` enforce:
  `Model::preventLazyLoading(! $this->app->isProduction());`

## 5. Database Standards

- Define explicit foreign keys and constraints where appropriate.
- Use `NOT NULL`, defaults, and `UNIQUE` only to enforce genuine integrity requirements.
- Index foreign keys and frequently queried columns.
- Name composite indexes `idx_<table>_<columns>`, based on **actual query patterns** (especially combined `WHERE` + `ORDER BY`).
- Avoid duplicate or unnecessary indexes and over-indexing write-heavy tables.
- Use short `DB::transaction()` blocks for atomic multi-step writes. **Never make external HTTP/API calls inside a transaction.**
- Use `lockForUpdate()` or atomic DB operations when concurrent updates could violate integrity.

## 6. API Contract, Responses & Error Handling

- **Base path:** `/api/v1` | **Format:** JSON (`Accept: application/json`) | **Timestamps:** ISO 8601 UTC.
- **Localization:** support `Accept-Language` (`en`, `ar`) for validation and response messages.
- **Success envelope:** follow the existing project convention. If none exists, use `{ "data": {}, "meta": {} }`. Keep `meta` meaningful (pagination, relevant metadata), with no redundant structures.
- Use `JsonResource` API Resources for all output and `whenLoaded()` for conditional relationships.
- **Pagination:** `cursorPaginate()` for high-growth or sequential streams. Bounded page pagination for standard collections: `min($request->integer('per_page', 15), 100)`.
- **Unified error envelope** (configured globally in `bootstrap/app.php`):
  `{ "error": { "code": "<error_code>", "message": "<human_readable>", "details": {} } }`

| Status | `code` | Notes |
|---|---|---|
| 400 | `bad_request` | |
| 401 | `unauthenticated` | |
| 403 | `forbidden` | |
| 404 | `not_found` | |
| 409 | `conflict` | |
| 422 | `validation_failed` | field errors in `details` |
| 429 | `rate_limited` | include `Retry-After` header |
| 500 | `server_error` | **never** expose exceptions, SQL, stack traces, credentials, or env variables |

## 7. Security & Performance

- **Authorization:** enforce resource-level Policies (`$this->authorize(...)`) on every protected action to prevent IDOR. Never trust client-supplied IDs without verifying ownership/membership.
- **Uploads:** strictly validate MIME type, extension, and max size. Store on private disks and serve through authorized download endpoints.
- **Rate limiting:** apply `throttle` to sensitive endpoints (auth, uploads, external API calls).
- **Performance:** prevent N+1 with `with()` / `loadMissing()`, select only needed columns on list endpoints, and never run unbounded `->get()` on large tables.

## 8. Testing Standards

- Use **Pest**.
- **Feature tests** for API behavior, authentication, authorization, validation, and integration boundaries. **Unit tests** where isolated logic benefits.
- Cover: success paths, validation failures (`422`), unauthenticated (`401`), unauthorized/IDOR (`403`), and relevant business edge cases (`409`/`422`).
- Fake external services and infrastructure: `Http::fake()`, `Storage::fake()`, `Queue::fake()`, etc.
- Run relevant tests after each change and the full suite before considering a feature complete.
- **Never remove or weaken tests merely to make an implementation pass.**

## 9. Documentation & Postman Deliverables

- At the end of each slice, create/update a valid **Postman Collection v2.1** at `postman/<slice_name>.postman_collection.json`:
  - Variables `{{base_url}}` and `{{token}}`.
  - Headers `Accept: application/json` and `Accept-Language: en`.
  - Sample request bodies and responses for **every** endpoint in the slice.
- Keep OpenAPI documentation (Scramble) and `README.md` accurate.

## 10. Git & Repository Safety

- Use **Conventional Commits**: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`.
- Before concluding a slice, run `./vendor/bin/pint` and the relevant Pest tests.
- **Never** commit, push, merge, reset, force-push, or delete branches unless explicitly instructed.
- Never discard, overwrite, or revert existing user changes without explicit approval.
- Explain the impact of any potentially destructive command **before** running it.
