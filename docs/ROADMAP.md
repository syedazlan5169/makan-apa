# MakanApa? — Roadmap

> This is a direction document, not an instruction to implement everything. Features should be selected deliberately one development phase at a time.

## Completed — Office Windows PC Phase

The Office-PC phase is intentionally frozen after delivering:

- Dockerized Laravel development environment;
- MySQL and Redis services;
- Vite/Node development service;
- Docker named volumes for heavy dependencies;
- menu categories/items seed data;
- basic category filtering;
- weighted meal recommendation service;
- accepted meal history;
- Redis-backed temporary current-session rejection memory;
- persistent rejection history;
- recency and rejection weighting;
- recent choices UI;
- professional English styling.

Do not add more Office-PC features before the repository is committed and pushed unless fixing a genuine bug.

## Suggested MacBook Phase

Choose a small subset rather than automatically implementing every item.

### Completed — Anonymous Menu Submission Moderation

Visitors can suggest a menu item in an existing active category. Suggestions
remain pending until an authenticated admin approves or rejects them. Approval
creates or reuses an active approved menu item, which then naturally becomes
eligible for the roulette. This phase also introduced a minimal admin-only
Laravel session login and controlled CLI bootstrap for the initial admin.

### 1. Proper Member Accounts and Profiles

Add member registration/login and replace repeated free-text identity with a
lightweight user/person profile system.

Possible behavior:

```text
First visit
  -> choose/create profile
  -> remember profile in browser/session
  -> future visits default to that person
```

Historical `person_name` data must be migrated or mapped carefully.

Future members may be associated with menu submissions and view their own
submission history. The moderation schema already has nullable `user_id` for
this, but no member UI or workflow exists yet.

### 2. Real Menu Management

Build category/menu-item CRUD for maintaining the restaurant's actual large menu.

Potential features:

```text
Admin/menu screen
  -> category CRUD
  -> menu item CRUD
  -> price
  -> description
  -> active/inactive
```

Avoid forcing full authentication solely for this feature unless user management is being developed at the same time.

### 3. Useful Filters

Possible additions:

- maximum budget;
- selected categories/multiple categories;
- vegetarian/other future tags;
- exclude unavailable items.

Pseudo-flow:

```text
eligibleItems = activeItems
eligibleItems = applyCategoryFilter(eligibleItems)
eligibleItems = applyBudgetFilter(eligibleItems)
eligibleItems = excludeSessionRejects(eligibleItems)
recommendation = weightedPick(eligibleItems, userHistory)
```

### 4. Recommendation Explanation

Optionally show a subtle reason such as:

```text
"You haven't chosen this recently"
```

Do not expose raw scoring numbers unless useful for debugging/admin purposes.

## Suggested Home Windows PC Phase

Potential larger features:

### 1. Favorites

A favorite should increase probability, not necessarily force selection.

Example pseudo-rule:

```text
if item is favorite:
    weight *= 1.20
```

### 2. Ratings

Allow users to rate meals after eating them and use rating as an additional recommendation signal.

### 3. Better Long-Term Preference Model

Potential signals:

```text
recency
persistent rejection frequency
favorite status
rating
category diversity
historical acceptance frequency
```

Each signal should remain independently understandable and testable.

### 4. Statistics

Examples:

- most chosen meals;
- most rejected meals;
- favorite category per person;
- meals not selected for a long time;
- office-wide popular choices.

Keep analytics separate from the primary meal-selection flow.

## Completed — VPS Production Docker Deployment

The production Docker deployment is complete and live behind the existing host Apache. It includes immutable PHP-FPM and Nginx images, Redis, host MySQL access through the dedicated Docker network, HTTPS, reverse proxying, health checks, restart recovery, explicit migrations, and digest-based deployment/rollback.

Automated MakanApa database backups remain intentionally deferred and are not yet configured. Queue workers and the scheduler remain deferred until the application has jobs or scheduled tasks that require them.

## Ideas Parking Lot

These are possibilities, not commitments:

- meal photos;
- restaurant availability/status;
- multiple restaurants;
- group/team roulette;
- shared office order list;
- lunch ordering deadline;
- price changes/history;
- menu import from CSV/Excel;
- PWA/mobile-friendly install;
- notifications;
- API endpoint for recommendations.

Do not let the parking lot turn a focused lunch-choice application into a generic food platform without a clear product reason.
