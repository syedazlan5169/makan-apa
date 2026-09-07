# MakanApa? — Architecture

## Development Request Architecture

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

### Menu Submission Moderation Service

`MenuSubmissionModerationService` owns approval and rejection workflows. It
keeps controllers focused on HTTP behavior and makes the state-changing
workflow transactional.

Approval and public submission creation coordinate by locking the selected
menu-category row. A submission review additionally locks its own row and can
transition only from `pending`. The unique `(menu_category_id, name)` index on
`menu_items` remains the final integrity guarantee when creating or reusing an
approved item.

### Authentication and Authorization

Laravel's built-in `web` session guard provides the minimal authentication
needed for administration. `EnsureUserIsAdmin` protects moderation routes
after the normal `auth` middleware. Roles are application-level strings; the
current values are `admin` and `member`, with only admin behavior implemented.

There is no public registration route. The first administrator is created
through the controlled `php artisan makan:create-admin` command.

### Models

Eloquent models represent stored domain entities and relationships.

Avoid placing large workflows directly in model event hooks unless the behavior is truly intrinsic to every save of that model.

### MySQL

Use for durable facts that should survive browsers, sessions, container recreation, and machine restarts.

Current examples:

- menu categories;
- approved menu items;
- menu submissions and their moderation history;
- users and roles;
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

The local Compose environment remains a development environment. In particular, `php artisan serve`, source bind mounts, the MySQL development container, and the Vite development server are appropriate for local development but are not part of the production request path.

## Production Request Architecture

```text
Internet
   |
   v
Host Apache :80/:443
   |  TLS termination and reverse proxy
   v
127.0.0.1:8086
   |
   v
Docker Nginx
   |  static files / FastCGI
   v
Docker PHP-FPM Laravel app
   |                         |
   v                         v
Docker Redis              host.docker.internal:3306
                                 |
                                 v
                            Host MySQL
```

Apache remains the public web server because the VPS hosts existing non-Docker applications. Its HTTP virtual host redirects permanently to HTTPS; the Certbot-managed HTTPS virtual host covers both MakanApa hostnames and proxies to `http://127.0.0.1:8086/` with `ProxyPreserveHost On`. Apache sets `X-Forwarded-Proto: https` and `X-Forwarded-Port: 443` so Laravel receives the original secure-request context.

Only Docker Nginx publishes a host port, bound deliberately to `127.0.0.1:8086:80`. PHP-FPM (`app:9000`) and Redis (`redis:6379`) are internal to the dedicated Docker network. Laravel trusts only proxy addresses from `172.30.10.0/24`; it must not trust all proxies unless the architecture changes.

Nginx serves static public assets and forwards only `/index.php` to PHP-FPM over FastCGI. Other `.php` requests return `404`, and hidden files are denied. It uses Docker's embedded resolver (`127.0.0.11`) to resolve `app:9000` at request time, allowing the app container to start or restart independently of Nginx startup.

## Production Data and Network Architecture

Production Compose defines exactly three services: `nginx`, `app`, and `redis`. MySQL remains on the host rather than in the Compose stack. The app reaches it through `host.docker.internal:3306`, provided by Compose's `host-gateway` extra-host mapping.

The `makanapa_prod` network deliberately uses `172.30.10.0/24`, selected after checking existing host routes to avoid collisions. The host MySQL account is limited to `makanapa.*` and the `makanapa`@`172.30.10.0/24` source subnet. This isolates MakanApa database access from containers on Docker's default network while preserving the host MySQL accessibility required by other VPS applications.

Redis uses the `redis_data` named volume and stores Laravel sessions, cache, and the configured queue backend. Its persistence supports routine container recreation, but it is not a database or backup. Production has no queue worker or scheduler because the application currently has no jobs or scheduled tasks. Future workers or schedulers must reuse the same application image.

## Production Image Architecture

`Dockerfile.prod` is a multi-stage build with `php-base`, `composer-dependencies`, `frontend-build`, `production`, and `nginx-production` stages. Images are built as `linux/amd64` for the VPS, including when Docker Buildx builds them from an Apple Silicon machine.

The `production` image contains PHP 8.4 FPM, Laravel source, production Composer dependencies, and compiled Vite assets. It excludes Node/npm, Composer, and the build toolchain. Required Laravel writable directories are prepared during the build. Production code is immutable inside the image; no source-code bind mount is used.

The `nginx-production` image contains only the Nginx-required public assets: `index.php`, `favicon.ico`, `robots.txt`, and `public/build`. It does not contain the Laravel source tree, PHP, Composer, or Node. Both images obtain matching compiled assets from the shared frontend-build stage.

`public/hot` and `public/fonts-manifest.dev.json` are excluded from the build context and removed again from the final application image. This prevents Laravel Vite from mistaking a production image for an active development-server environment.

## Production Configuration, Release, and Recovery

The VPS deployment directory is `/opt/makanapa`. Its `.env` provides only Compose image interpolation (`APP_IMAGE` and `NGINX_IMAGE`), while `/opt/makanapa/shared/laravel.env` is the mode-600, server-local Laravel runtime configuration and secret file injected with `env_file`. The committed template is [`docker/production/laravel.env.example`](../docker/production/laravel.env.example); real values are never committed or documented.

Production images are pulled from GHCR by immutable digest. Application and Nginx images can be replaced independently, allowing a release or rollback without rebuilding on the VPS. Database migrations are intentionally explicit release operations, not container-startup behavior. A corresponding database rollback or compatibility assessment remains separate from image rollback.

All services use `restart: unless-stopped`. Redis health is checked with `redis-cli ping`, the app healthcheck verifies its local PHP-FPM port, and Nginx checks `/robots.txt`. Cold startup is Redis healthy -> app healthy -> Nginx starts. `depends_on` provides this startup coordination only; Docker Engine restart policies recover containers after a Docker daemon restart.

Post-deployment HTTP smoke testing through the public HTTPS endpoint and app/Nginx log review provide application-level verification beyond container health checks.

Automated MakanApa database backups are intentionally deferred and not yet configured. The Redis volume is not a backup.
