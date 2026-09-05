# MakanApa? — Important Decisions

This is a lightweight Architecture Decision Record. Add dated entries when a choice would otherwise be easy for a future developer/AI agent to accidentally reverse.

## D001 — Docker Is the Primary Development Runtime

**Status:** Accepted

Project-specific PHP, Composer, Node.js, MySQL, and Redis are provided through Docker rather than installed and managed independently on every development machine.

**Reason:** The project doubles as a Docker learning project and must behave consistently across Office Windows, MacBook, Home Windows, and later VPS environments.

---

## D002 — Source Uses Bind Mounts; Heavy Dependencies Use Named Volumes

**Status:** Accepted

Human-edited source is bind-mounted for live editing. `vendor/` and `node_modules/` use Docker named volumes.

**Reason:** On the Office Windows PC, bind-mounted Composer `vendor/` caused extremely slow Laravel requests. Moving dependencies to Docker's Linux-side storage made requests almost instant.

---

## D003 — MySQL Stores Permanent History; Redis Stores Current Roulette State

**Status:** Accepted

MySQL stores accepted meals and persistent rejection events. Redis-backed Laravel sessions store rejected menu IDs for the current decision session.

**Reason:** Losing Redis session state should at worst reset an in-progress roulette. It must not erase durable preference/history data.

---

## D004 — Recommendation Uses Explainable Weighted Randomness

**Status:** Accepted

The recommendation engine uses explicit business rules and weighted random selection rather than AI/ML.

**Reason:** The dataset is small, rules are understandable, behavior is easy to tune, and randomness is desirable for meal discovery.

---

## D005 — Recommendation Logic Lives Outside the Controller

**Status:** Accepted

Recommendation rules live in `MenuRecommendationService` rather than expanding inside `MakanController`.

**Reason:** Keeps HTTP orchestration separate from domain logic and makes future testing/reuse easier.

---

## D006 — Identity Remains String-Based at End of Office-PC Phase

**Status:** Temporary / intentionally deferred

Current history uses `person_name` strings rather than a foreign key to a users table.

**Reason:** Proper profiles/authentication are intentionally reserved for a later machine/development phase. Future migration must preserve Office-PC history.

---

## D007 — Application UI Uses Professional English

**Status:** Accepted

Developer conversation may be informal, but application-facing copy should remain professional English unless product requirements change.

**Reason:** The application is intended to feel like a real usable office tool rather than a tutorial/demo interface.

## D008 - Cross-platform Vite file watching

Windows Docker Desktop required polling for reliable Vite file watching.
macOS Docker Desktop works correctly using native filesystem events.

VITE_USE_POLLING controls the behavior:
- false by default
- true on Windows machines where bind-mount events are unreliable

Vite CORS must allow http://localhost:8085 so the Laravel page can load
the Vite development client from http://localhost:5173 and use HMR.

---

## D009 — Host Apache Remains the Public TLS Frontend

**Status:** Accepted

The VPS host Apache remains responsible for public HTTP/HTTPS handling, TLS termination, and reverse proxying to Docker Nginx on loopback.

**Reason:** The VPS already hosts several non-Docker applications behind Apache. Replacing it with Docker Nginx would disrupt that established host architecture. Loopback proxying keeps the Docker application private to Apache.

---

## D010 — Production Uses Host MySQL Through a Restricted Docker Network

**Status:** Accepted

Production uses the existing host MySQL instance rather than a MySQL container. The app reaches it through `host.docker.internal`, backed by Docker's `host-gateway` mapping. The dedicated `172.30.10.0/24` Docker subnet was selected to avoid host-route collisions, and the MySQL account is restricted to `makanapa.*` from that subnet.

**Reason:** Host MySQL is already required by other VPS applications. The dedicated subnet and source-restricted account allow only the intended production Docker network to access the MakanApa database without changing the existing host MySQL exposure model.

---

## D011 — Only Docker Nginx Is Published, on Loopback

**Status:** Accepted

Docker publishes only Nginx as `127.0.0.1:8086:80`. PHP-FPM and Redis have no host-published ports.

**Reason:** Apache is the public ingress and TLS endpoint. Limiting Docker exposure to loopback keeps the internal FastCGI and Redis services inaccessible from the network.

---

## D012 — Production Releases Use Immutable, Independent Images

**Status:** Accepted

The PHP-FPM application and Nginx images are built for `linux/amd64`, pushed to GHCR, and deployed by immutable digest. Production has no source-code bind mounts, and the two images are independently replaceable.

**Reason:** Immutable images make deployed code reproducible and support targeted replacement and rollback without building on the VPS. No production bind mount prevents host source changes from altering the running release.

---

## D013 — Migrations Are Explicit Release Operations

**Status:** Accepted

Database migrations are run explicitly with `php artisan migrate --force`, not during application container startup.

**Reason:** Starting or restarting a container must not unexpectedly mutate the schema. Image rollback does not reverse database changes, so migrations require deliberate release-time compatibility and rollback consideration.

---

## D014 — Queue Workers and Scheduler Wait for Real Work

**Status:** Accepted

Production has no queue worker or scheduler service until the application dispatches jobs or defines scheduled tasks. When needed, both must reuse the same application image.

**Reason:** Extra long-running services should follow a real application need rather than exist as unused deployment complexity.

---

## D015 — Production Docker Commands Use `sudo`

**Status:** Accepted

The deployment user is intentionally not a member of the Docker group; production Docker commands use `sudo docker ...`.

**Reason:** Docker-group membership effectively grants root-level host control. Explicit elevation keeps that privilege boundary visible.

---

## D016 — Laravel Trusts Only the Dedicated Proxy Subnet

**Status:** Accepted

Laravel trusts proxies only from `172.30.10.0/24`, and production enables `SESSION_SECURE_COOKIE=true`.

**Reason:** Apache terminates HTTPS before Docker Nginx and PHP-FPM, so forwarded HTTPS information must be trusted for correct URL and cookie generation. Restricting trust to the dedicated proxy network avoids accepting forwarded headers from arbitrary sources.

---

## D017 — Vite Development Artifacts Are Forbidden in Production Images

**Status:** Accepted

`public/hot` and `public/fonts-manifest.dev.json` are excluded through `.dockerignore`; the final production image also removes them defensively.

**Reason:** `public/hot` makes Laravel Vite generate browser asset URLs for a local development server. The production incident fixed by `011a50d` showed that this breaks real browsers and mobile clients. Defense in depth keeps this development state out of deployable images.

---

## D018 — Real Menu Seeding Is Transactional and Idempotent

**Status:** Accepted

`MenuSeeder` uses a transaction, `MenuCategory::firstOrCreate()`, and `MenuItem::updateOrCreate()` for matching category and item names.

**Reason:** Production seeding can be rerun without duplicating matching menu data. This approach does not delete records later removed from the seeder; a future synchronization policy must make deletion explicit.

---

## D019 — Database Backup Implementation Is Deferred

**Status:** Deferred

MakanApa database backups are not yet configured. Redis persistence is not treated as a backup.

**Reason:** Backup design and operation will be configured separately. Documentation must state this gap accurately rather than imply existing coverage.