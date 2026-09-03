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