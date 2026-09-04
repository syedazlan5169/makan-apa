# MakanApa? — Current State

> This file records the exact hand-off point at the end of the Office-PC development phase. Update it whenever a development phase is completed.

## Application Stack

- Laravel 13.x
- PHP 8.4 CLI development container
- MySQL 8.4
- Redis 7 Alpine
- Node.js 24 Alpine
- Vite 8.x
- Tailwind CSS via Vite
- Docker Compose

`compose.yaml` is the source of truth for exact image tags, ports, services, and volume names.

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

## Production Images

`Dockerfile.prod` produces two independent immutable images (no Compose wiring yet):

```text
docker build -f Dockerfile.prod --target production -t makan-apa-app .
docker build -f Dockerfile.prod --target nginx-production -t makan-apa-nginx .
```

### `production` (PHP-FPM app image)

Contains: Laravel source, production Composer dependencies, compiled Vite assets, PHP-FPM. Runs `php-fpm`. Unchanged by the Nginx work below.

### `nginx-production` (Nginx image)

Built from `nginx:1.30.4-alpine3.24`. Reuses the same `frontend-build` stage as `production`; both image targets build from that one stage, and BuildKit can reuse its cached result when the frontend inputs are unchanged, so both resulting images receive matching compiled Vite assets. Its `public/` directory contains exactly:

```text
index.php
favicon.ico
robots.txt
build/
```

`index.php` is present only so Nginx's `try_files` resolves it as a real file — Nginx never executes PHP. Config lives at `docker/nginx/default.conf` and forwards only `location = /index.php` to `app:9000` over FastCGI (all other `.php` paths return 404). The upstream is resolved at request time via Docker's embedded DNS (`resolver 127.0.0.11`) rather than at Nginx startup, so `app` can start, restart, or be temporarily unavailable without failing Nginx's own startup. Access/error logs go to stdout/stderr. No ports are published in the Dockerfile.

Not yet implemented: Docker Compose service definitions for `nginx-production` + `app`, the host Apache reverse proxy, and TLS.

## Current Routes

```text
GET  /        -> MakanController@index
POST /pick    -> MakanController@pick
POST /accept  -> MakanController@accept
POST /switch  -> MakanController@switchUser
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

Identity is still based on a plain `person_name` string. This is intentionally left for a future development phase. Do not silently introduce authentication or a user table while working on unrelated tasks.
