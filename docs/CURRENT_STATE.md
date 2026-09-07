# MakanApa? — Current State

> This file records the exact hand-off point after the completed production Docker deployment. Update it whenever a development or deployment phase is completed.

## Deployment Status

Production is live at:

```text
https://makanapa.sydigitalsolution.com
```

`https://www.makanapa.sydigitalsolution.com` is also supported. Both DNS names point to DigitalOcean Reserved IP `146.190.6.224`, which routes to the production Droplet at public IPv4 `152.42.188.223`. HTTP redirects permanently to HTTPS.

The VPS runs Ubuntu 22.04.5 LTS on `onlinekad` (`amd64` / `x86_64`, 2 vCPU, about 3.8 GiB RAM, no swap), with Docker Engine 29.7.2 and Docker Compose v5.5.0.

The current production release is main merge commit `dcac85f` (Menu Submission
Moderation and custom favicon):

```text
Application: ghcr.io/syedazlan5169/makan-apa@sha256:2271e63d1f447eb0f8b628d8ffa803c6ea3c94dd390cdd11d0975576312e2f04
Nginx:       ghcr.io/syedazlan5169/makan-apa-nginx@sha256:5a33896b99f41293b61ce82d51e46ad88849a4241ce53b12ae5440ee4abc69db
```

Production has been verified through the public URL: HTTP-to-HTTPS redirect,
HTTPS HTTP/2 response, `www` hostname, generated HTTPS URLs, secure cookies,
compiled CSS/JS assets, Redis and host-MySQL connectivity, health checks,
Docker daemon restart recovery, app-only release replacement, homepage,
favicon, anonymous menu suggestion, pending moderation, admin login, approval,
roulette eligibility for approved items, and rejected items staying outside the
roulette.

## Application Stack

- Laravel 13.x
- PHP 8.4
- MySQL 8.4 in development; existing host MySQL in production
- Redis 7 Alpine
- Node.js 24 Alpine for development and image builds
- Vite 8.x
- Tailwind CSS via Vite
- Docker Compose

`compose.yaml` is the source of truth for the local development environment. `compose.prod.yaml` is the source of truth for the production Docker stack.

## Docker Services

### `app`

Runs Laravel using the PHP development server:

```text
php artisan serve --host=0.0.0.0 --port=8000
```

The project source is bind-mounted into `/var/www/html`.

Composer dependencies are intentionally stored in a Docker named volume mounted at:

```text
/var/www/html/vendor
```

This is important on Docker Desktop for Windows. Keeping `vendor/` on the Windows bind mount caused very slow Laravel requests because Laravel performs many filesystem reads. Moving `vendor/` to Linux-side Docker storage reduced page loads from several seconds to almost instant.

### `db`

MySQL 8.4.

Application connection uses the Compose service hostname:

```text
DB_HOST=db
```

Database files are stored in a named Docker volume.

### `redis`

Redis 7 Alpine.

Laravel connects using:

```text
REDIS_HOST=redis
```

Redis is currently used for cache/queue configuration and, importantly, Laravel sessions.

### `node`

Node.js/Vite development server.

`node_modules` is stored in a Docker named volume rather than the host bind mount.

Vite is exposed to the host on port `5173`.

## Important Vite Development Configuration

The Vite server needs Docker/Windows-friendly settings similar to:

```js
server: {
    host: "0.0.0.0",
    port: 5173,
    origin: "http://localhost:5173",
    hmr: {
        host: "localhost",
    },
    watch: {
        usePolling: true,
        interval: 500,
        ignored: ["**/storage/framework/views/**"],
    },
},
```

Reasons:

- `0.0.0.0` lets Vite listen inside the container.
- The browser-facing origin must be `localhost:5173`, not `0.0.0.0:5173`.
- Polling is enabled because file-change events from Windows bind mounts were not always detected reliably by Vite.

## Environment Configuration

Relevant `.env` values include the following concepts:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=makan_apa
DB_USERNAME=makan_apa

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

Never treat the real `.env` file as documentation or source-controlled configuration. `.env.example` should describe required keys without real secrets.

## Production Runtime

The production Compose project is `makanapa-prod` and runs `nginx`, `app`, and `redis`; there is deliberately no MySQL container, queue worker, or scheduler. `nginx` alone is host-published at `127.0.0.1:8086:80`; the app's PHP-FPM port `9000` and Redis port `6379` are internal to Docker only.

The deployment directory is:

```text
/opt/makanapa/
├── compose.prod.yaml
├── .env
└── shared/
    └── laravel.env
```

`/opt/makanapa/.env` is solely Docker Compose interpolation for `APP_IMAGE` and `NGINX_IMAGE`. It is not Laravel's runtime environment file. Laravel configuration and production secrets are in `/opt/makanapa/shared/laravel.env`, which is mode `600` and never committed. The committed template is [`docker/production/laravel.env.example`](../docker/production/laravel.env.example).

All services use `restart: unless-stopped`. Restarting the Docker daemon successfully restored all three healthy containers without another `docker compose up -d`; this recovery is provided by Docker Engine's restart policy. Cold startup is ordered Redis healthy -> app healthy -> Nginx starts. `depends_on` coordinates startup only; it does not provide full runtime orchestration.

Redis uses the `redis_data` named volume for session, cache, and queue-backend persistence across routine redeploys. The volume survived `docker compose down` followed by `docker compose up -d`. It is not a backup; do not casually run `docker compose down -v` in production.

## Production Release Operations

Production images are built for `linux/amd64` with Docker Buildx, pushed to GHCR, and deployed by immutable digest. Application and Nginx images are independently replaceable. A live release proved that changing only `APP_IMAGE` recreates only the app container while Nginx and Redis remain running.

The current release workflow is:

```text
1. Commit and push the release commit.
2. Build and push linux/amd64 images, then obtain their immutable digests.
3. On the VPS, pull the selected digests and update APP_IMAGE and/or NGINX_IMAGE in /opt/makanapa/.env.
4. Validate: sudo docker compose -f compose.prod.yaml config -q
5. When required, run: sudo docker compose -f compose.prod.yaml run --rm app php artisan migrate --force
6. Deploy: sudo docker compose -f compose.prod.yaml up -d
7. Check: sudo docker compose -f compose.prod.yaml ps
8. Smoke test: curl -I https://makanapa.sydigitalsolution.com/
9. Review app and Nginx logs.
```

Migrations are deliberately not run during container startup. They are an explicit release operation, and image rollback does not undo a database migration. Retain the previous immutable digest for rollback, assess database compatibility separately, then update the image variable and run `up -d`.

The deployment user is intentionally not in the Docker group because that membership grants root-level host control. Production Docker commands use `sudo docker ...`. The root Docker CLI uses a separate read-only GHCR package credential; its value is never documented.

## Production Vite Rule

`public/hot` must never exist in a production Laravel/Vite image. Its presence makes Laravel Vite treat a development server as active, causing browser asset URLs to point at `http://localhost:5173` instead of the deployed build assets.

This caused a production incident before release `011a50d`: browsers on Mac could hang or request local-network access, and mobile browsers rendered unstyled HTML because `localhost` referred to the client device. The permanent fix excludes `public/hot` and `public/fonts-manifest.dev.json` in `.dockerignore` and removes both defensively in the final production app image. Production-image validation confirmed both artifacts absent and `public/build/manifest.json` present; live HTML now references only deployed HTTPS `/build/assets/...` URLs.

## Production Data State

The initial production migrations have run. The production database was seeded from the real restaurant menu in `MenuSeeder`: it now contains 12 categories and 197 items, and the homepage has been verified with categories including `Ayam & Daging`, `Mee / Bihun / Kuey Teow / Maggi`, `Nasi Goreng`, and `Tomyam`.

`DatabaseSeeder` calls `MenuSeeder`. The menu seeder runs inside a transaction and is idempotent for matching category/item names through `firstOrCreate()` and `updateOrCreate()`. It prevents duplicate matching records but does not delete records removed from the seeder later; any future menu synchronization needs an explicit deletion policy.

Menu Submission Moderation is live in production. The `users.role`,
`menu_submissions`, and `menu_items(menu_category_id, name)` unique-index
migrations completed successfully. The required duplicate preflight was clean
before the unique index was applied. Future deployments that add a comparable
menu-item uniqueness migration must still preflight production data using the
MySQL collation, including legacy internal/repeated whitespace, and resolve any
duplicates deliberately rather than automatically deleting data.

The initial production administrator was created successfully through:

```bash
php artisan makan:create-admin
```

## Backup Status

Automated database backups are deferred and not yet configured for MakanApa. The existing OnlineKad backup process does not protect the `makanapa` database. Redis persistence is not a backup.

## Current Routes

```text
GET  /        -> MakanController@index
POST /pick    -> MakanController@pick
POST /accept  -> MakanController@accept
POST /switch  -> MakanController@switchUser
GET  /suggest-menu -> MenuSubmissionController@create
POST /suggest-menu -> MenuSubmissionController@store (5 requests/minute/IP)
GET  /admin/login -> AdminAuthenticatedSessionController@create (guest only)
POST /admin/login -> AdminAuthenticatedSessionController@store (guest only, rate-limited)
POST /admin/logout -> AdminAuthenticatedSessionController@destroy (auth + admin)
GET  /admin/menu-submissions -> AdminMenuSubmissionController@index (auth + admin)
PATCH /admin/menu-submissions/{menuSubmission}/approve -> AdminMenuSubmissionController@approve (auth + admin)
PATCH /admin/menu-submissions/{menuSubmission}/reject -> AdminMenuSubmissionController@reject (auth + admin)
```

## Current Domain Models

### MenuCategory

Key fields:

```text
id
name
is_active
timestamps
```

Relationship:

```text
MenuCategory hasMany MenuItem
```

### MenuItem

Key fields:

```text
id
menu_category_id
name
description nullable
price nullable
is_active
timestamps
```

Relationships:

```text
MenuItem belongsTo MenuCategory
MenuItem hasMany MealChoice
MenuItem hasMany MenuSubmission
```

`menu_items` contains approved menu items only. Its `(menu_category_id, name)`
unique index is the final integrity guarantee for approved item identity.

### MenuSubmission

Records an anonymous or future authenticated-member menu suggestion and its
moderation history.

Key fields:

```text
id
menu_category_id
name (normalized surrounding/repeated whitespace)
user_id nullable
status (pending, approved, rejected)
menu_item_id nullable
reviewed_by nullable
reviewed_at nullable
review_notes nullable
timestamps
```

Relationships:

```text
MenuSubmission belongsTo MenuCategory
MenuSubmission belongsTo optional submitting User
MenuSubmission belongsTo optional resulting MenuItem
MenuSubmission belongsTo optional reviewing User
```

Anonymous submissions have `user_id = null`. Pending and rejected submissions
never enter the roulette because the recommendation service queries only active
`MenuItem` records.

### User Roles and Admin Access

`users.role` is a normal string with a `member` default. Current application
roles are `admin` and `member`, but only admin behavior is implemented.

Admin authentication uses Laravel's `web` session guard. Login validates admin
credentials, regenerates the session, and is rate-limited. Logout is POST-only,
logs out, invalidates the session, and regenerates the CSRF token. Admin routes
require both `auth` and `admin` middleware.

There is no public registration route and no member profile, login, or
submission-history UI. Create the first admin with:

```bash
docker compose exec app php artisan makan:create-admin
```

### MealChoice

Records a meal the user actually accepted.

Key fields:

```text
id
person_name
menu_item_id
chosen_at
timestamps
```

### MealRejection

Records a meal explicitly rejected by clicking **Pick something else**.

Key fields:

```text
id
person_name
menu_item_id
rejected_at
timestamps
```

Indexes exist for useful person/item and rejection-time lookups.

## Current Recommendation Logic

Recommendation/business logic lives in:

```text
app/Services/MenuRecommendationService.php
```

Do not move this logic back into the controller unless there is a strong architectural reason.

### Eligibility

An item must:

- be active;
- match the optional selected category;
- not be in the current roulette session's rejected-item list.

Menu submissions are not eligible directly. Approval transactionally creates
or safely reuses an active `MenuItem`, links it to the submission, and records
the reviewing admin and time. Rejection records the review without creating a
menu item. Submission creation and approval coordinate through category locks;
reviews lock the submission and only pending records can transition.

### Session State

Laravel sessions are stored in Redis. The following keys are used:

**`makan.current_person_name`** (authoritative identity)
- Stores the current person's name (trimmed string, max 50 chars).
- Set whenever a valid roulette request is made.
- Identity validation ensures request person_name matches this session value.
- If person_name differs from session, request is rejected with a validation error.
- When identity changes or is first set, `makan.rejected_item_ids` is automatically cleared to prevent roulette state leakage between people.
- Cleared only by explicit "Switch user" action (POST /switch).
- Session lifetime: 120 minutes (idle timeout).

**`makan.rejected_item_ids`** (temporary roulette state)
- Array of menu item IDs rejected during the current roulette session.
- Filled when user clicks "Pick something else".
- Cleared when user accepts a meal or starts a new roulette session.
- Cleared automatically when person identity changes (prevents state leakage).
- Does not persist across browser sessions or cookie clearing.

### Temporary Rejection Memory (Legacy Naming)

During a roulette session, rejected menu IDs are stored in the Laravel session under:

```text
makan.rejected_item_ids
```

Sessions use Redis.

When a user accepts a meal, this temporary rejected-item list is cleared.

### Recency Weight

The last accepted date affects the base weight approximately as follows:

```text
Never eaten / 30+ days     100
14–29 days                  80
7–13 days                   60
3–6 days                    35
1–2 days                    15
Today                         5
```

Recently eaten meals remain possible but become much less likely.

### Rejection Penalty

Persistent rejections from roughly the previous 30 days further multiply the weight:

```text
0 rejections       x 1.00
1 rejection        x 0.80
2 rejections       x 0.60
3–4 rejections     x 0.40
5+ rejections      x 0.20
```

The final recommendation uses weighted random selection.

## Current UI

The home page is intentionally:

- English language;
- professional rather than joke-heavy;
- light themed;
- responsive;
- centered around a single fast decision flow.

The main UI currently contains:

- MakanApa? branding;
- user name input (or remembered name display);
- category selector;
- **Choose for me** button;
- **Switch user** button (only shown if name is remembered);
- recommendation card;
- **I'll have this** action;
- **Pick something else** action;
- **Suggest a menu** entry point and public form;
- skipped-option count during the current decision;
- recent accepted choices.

### Remembered Name Behavior

When a user enters their name and clicks "Choose for me", the name is remembered in the browser session (Redis-backed).

On subsequent visits in the same browser session (within 120 minutes of activity):
- The remembered name is displayed prominently instead of an empty input field.
- A "Switch user" button allows explicit identity changes.
- The category selector and "Choose for me" button remain visible.

When "Switch user" is clicked:
- Both `makan.current_person_name` and `makan.rejected_item_ids` are cleared from the session.
- The page redirects to the home page.
- User sees an empty name input field again, ready to enter a new identity.

If the user manually tries to change the name in the form during a roulette session (edge case):
- The form submission fails with a validation error.
- Error message: "Identity mismatch. Please use 'Switch user' to change."
- This prevents accidental roulette state leakage.

## Known Temporary Limitation

Roulette identity and meal history remain based on a plain `person_name`
string. This is intentionally independent from the new admin authentication
boundary. Future member accounts must map or migrate historical name-based data
carefully; member registration, profiles, and submission-history UI are not
implemented.

## Verification Status

The Menu Submission Moderation feature has passed focused moderation/admin-auth
tests (22 tests, 99 assertions), the full Laravel suite (37 tests, 139
assertions), `git diff --check`, and manual local testing of submission,
moderation, and roulette eligibility flows. It is now live in production, where
manual verification passed for the homepage, favicon, anonymous suggestion,
pending moderation, admin login, approval, roulette eligibility, and rejection.
