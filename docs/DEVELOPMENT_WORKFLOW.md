# MakanApa? — Development Workflow

## Goal

Any developer or AI coding agent should be able to clone this repository onto a new machine, start the Docker environment, understand the current architecture, and continue development without relying on hidden host-machine configuration.

## Before Starting a Feature

1. Read all files in `/docs`.
2. Read the current relevant implementation before proposing changes.
3. Confirm the requested feature and identify which layer it belongs to.
4. Produce a short implementation plan before making broad changes.
5. Prefer the smallest coherent change that satisfies the feature.

## Normal Development Commands

Use Docker-based commands rather than host PHP/Composer/Node installations.

Examples:

```bash
# Start/reconcile the development environment
docker compose up -d

# Rebuild app image after Dockerfile changes
docker compose up -d --build

# Laravel Artisan
docker compose exec app php artisan <command>

# Composer
docker compose exec app composer <command>

# If app cannot stay up because vendor is missing
docker compose run --rm app composer install

# Node/npm
docker compose run --rm node npm install

# Logs
docker compose logs app
docker compose logs node

# MySQL shell
docker compose exec db mysql -u<user> -p <database>

# Redis check
docker compose exec redis redis-cli ping
```

Exact credentials and host port mappings should come from the local `.env`/Compose configuration, not from assumptions in this document.

## When a Rebuild Is Required

### Usually no rebuild

Changes to bind-mounted application source normally do not require an image rebuild:

```text
app/
routes/
resources/views/
config/
database/
docs/
```

### Vite/CSS changes

Vite should detect changes using polling. If front-end changes do not appear, first inspect Vite logs and then restart only the Node service if necessary:

```bash
docker compose restart node
```

### Dockerfile changes

Require rebuilding the affected image:

```bash
docker compose up -d --build
```

### Compose changes

Usually require reconciling/recreating containers:

```bash
docker compose up -d
```

## Database Changes

For new schema changes:

1. Create a new migration.
2. Do not rewrite old shared migrations merely to make history prettier after they have been committed and used by others.
3. Run migrations inside the app container.
4. Confirm the application still boots.

Example:

```bash
docker compose exec app php artisan migrate
```

## Dependency Changes

### PHP

Update both:

```text
composer.json
composer.lock
```

Do not commit `vendor/`.

### JavaScript

Update both:

```text
package.json
package-lock.json
```

Do not commit `node_modules/`.

## Validation After a Feature

At minimum:

- confirm containers are healthy/running;
- exercise the changed user flow manually;
- run relevant Laravel tests if they exist;
- run new migrations when required;
- check application logs for new errors;
- verify no unrelated behavior was broken.

As automated testing is introduced, prefer adding tests for business rules in the recommendation service and HTTP tests for core user flows.

## Documentation Rule

Update `/docs` when a change affects any of the following:

- architecture;
- data model;
- Docker environment;
- recommendation rules;
- workflow;
- current completed phase;
- planned roadmap or important design decisions.

Documentation should describe the repository as it actually exists, not what someone hopes to implement later.
