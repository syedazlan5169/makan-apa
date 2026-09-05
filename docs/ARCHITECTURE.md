# MakanApa? — Architecture

## High-Level Architecture

```text
Browser
   |
   | HTTP
   v
Laravel app container
   |
   +------> MySQL container
   |          Permanent application data
   |
   +------> Redis container
   |          Temporary/session state
   |
   +<-----> Vite/Node container during development
              Front-end assets + HMR
```

## Responsibility Boundaries

### Controller

`MakanController` should coordinate HTTP behavior:

- validate input;
- read/write session state;
- invoke recommendation/domain services;
- load view data;
- persist straightforward user actions;
- redirect/render responses.

Controllers should not become the permanent home of a large recommendation algorithm.

### Recommendation Service

`MenuRecommendationService` owns meal-selection business rules.

Examples of logic that belongs here or in future dedicated recommendation rules:

- eligibility;
- recency weighting;
- rejection weighting;
- future preference/favorite weighting;
- weighted random selection.

The service should remain callable independently of the Blade view so that a future API/mobile interface can reuse it.

### Models

Eloquent models represent stored domain entities and relationships.

Avoid placing large workflows directly in model event hooks unless the behavior is truly intrinsic to every save of that model.

### MySQL

Use for durable facts that should survive browsers, sessions, container recreation, and machine restarts.

Current examples:

- menu categories;
- menu items;
- accepted meals;
- persistent rejection history.

### Redis

Use for temporary or fast-changing state that does not deserve permanent relational storage.

Current example:

- current roulette session rejected menu IDs.

If Redis is flushed, the user may lose an in-progress roulette session, but permanent meal history must remain intact.

## Storage Rules

### Source Code

Human-edited application files should be visible on the host through bind mounts during development.

Examples:

```text
app/
config/
database/
resources/
routes/
docs/
```

### Dependency Trees

Large machine-generated dependency directories should live in Docker named volumes in development, especially on Windows Docker Desktop.

Current examples:

```text
vendor/
node_modules/
```

This both improves filesystem performance and reinforces that dependencies are reproducible from lock files.

### Database Data

Raw database files belong in Docker named volumes, never in Git.

## Docker Is the Development Environment

Do not assume the host machine has project-specific versions of:

- PHP;
- Composer;
- MySQL;
- Redis;
- Node.js/npm.

Prefer commands such as:

```bash
docker compose exec app php artisan ...
docker compose exec app composer ...
docker compose run --rm node npm ...
docker compose exec db mysql ...
docker compose exec redis redis-cli ...
```

The host primarily needs Docker, Git, and an editor.

## Development vs Production

The current Docker setup is a development environment.

In particular:

```text
php artisan serve
```

is appropriate for development but is not the intended production architecture.

### Production Image Design

`Dockerfile.prod` is a multi-stage build producing two independent, immutable images from one file:

```text
production        PHP-FPM application image (source, prod Composer deps, compiled Vite assets, php-fpm)
nginx-production   Nginx image serving public/ and forwarding the front controller to php-fpm
```

`nginx-production` reuses the same `frontend-build` stage as `production`; both image targets build from that one stage, and BuildKit can reuse its cached result when the frontend inputs are unchanged, so both resulting images receive matching compiled Vite assets. The Nginx image intentionally contains only `public/index.php`, `public/favicon.ico`, `public/robots.txt`, and the compiled `public/build/` — no Composer/Node/PHP runtime and no other application source. `index.php` is present only so Nginx's `try_files` can resolve it as a real file; Nginx never executes PHP, it forwards the front controller to the `app` service on port 9000 over FastCGI.

Planned request flow once Compose is introduced for this phase:

```text
Host Apache (TLS, public entry point)
   |
   | reverse proxy, bound to localhost only
   v
Docker Nginx (nginx-production image)
   |
   | static files served directly
   | PHP/front-controller requests -> FastCGI
   v
PHP-FPM app container (production image)
   |
   +------> Docker Redis container
   |          Temporary/session state
   |
   +------> Host MySQL 8.0 (not containerized)
              Permanent application data, reached via host.docker.internal
```

Production database is deliberately **not** run in Docker. The `app` container reaches the existing host MySQL 8.0 instance through `host.docker.internal`, mapped via Docker's `host-gateway` extra host entry. The production Compose network uses the deliberately reserved subnet `172.30.10.0/24`, and the MySQL account is restricted to that subnet as `makanapa`@`172.30.10.0/24` (credentials are never documented here — see the real, non-committed `.env`).

### Production Compose

`compose.prod.yaml` defines exactly three services: `nginx`, `app`, `redis`. It has no `build:` entries (production never builds on the VPS) and no source bind mounts.

```text
docker compose -f compose.prod.yaml pull
docker compose -f compose.prod.yaml run --rm app php artisan migrate --force
docker compose -f compose.prod.yaml up -d
```

- `nginx` and `app` reference full image strings via `${NGINX_IMAGE}` / `${APP_IMAGE}` (e.g. `ghcr.io/syedazlan5169/makan-apa-nginx:024e18e`, `ghcr.io/syedazlan5169/makan-apa:024e18e`), so a rollback is a matter of changing these two variables to a prior tag or digest and re-running `pull`/`up -d` — no rebuild required.
- `nginx` publishes only `127.0.0.1:8086:80`. `app` and `redis` publish no ports; they are reachable solely via `app:9000` / `redis:6379` on the `makanapa_prod` network.
- `app` depends on `redis` with `condition: service_healthy`; `nginx` depends on `app` with `condition: service_healthy`. `depends_on` only sequences initial startup — it is not ongoing orchestration, so a later Redis restart does not automatically re-gate `app`.
- `app`'s healthcheck is a PHP `fsockopen` TCP probe against `127.0.0.1:9000` (no extra packages) — a liveness/readiness approximation, not a full application check. Real application health is verified by a post-deployment HTTP smoke test through `nginx`.
- `redis` uses a named volume (`redis_data`) for session/cache/queue continuity across routine redeploys — this is persistence, not backup, and holds no durable business data (`D003`).
- Laravel runtime configuration/secrets are injected into `app` via `env_file: shared/laravel.env`, a server-local file (never committed) at `/opt/makanapa/shared/laravel.env`. The committed template for that file is [`docker/production/laravel.env.example`](../docker/production/laravel.env.example). It sets `LOG_CHANNEL=stderr` (an existing Monolog channel, no new logging infrastructure) so application errors are visible via `docker compose logs app`.
- No queue worker or scheduler service exists yet — the codebase dispatches no jobs and defines no scheduled commands. Add them only when genuinely needed, reusing the same `app` image (no separate build).

Remaining production concerns for a future phase:

- secrets handling for `/opt/makanapa/shared/laravel.env` (creation/rotation);
- HTTPS termination at host Apache;
- host Apache reverse proxy configuration to `127.0.0.1:8086`;
- queues/workers, once real jobs exist;
- Laravel scheduler, once real scheduled tasks exist;
- FPM `ping`/`status` endpoints for a stronger app healthcheck;
- backups;
- logging/monitoring beyond container stdout/stderr.

Do not prematurely convert the current development Compose stack into production configuration during unrelated feature development.
