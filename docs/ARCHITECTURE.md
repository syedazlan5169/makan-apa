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

A future VPS phase should separately design production concerns such as:

- Nginx or another production web server;
- PHP-FPM;
- immutable application image;
- production dependency installation;
- secrets handling;
- HTTPS;
- persistent database/storage strategy;
- queues/workers;
- Laravel scheduler;
- health checks;
- backups;
- logging/monitoring.

Do not prematurely convert the current development Compose stack into production configuration during unrelated feature development.
