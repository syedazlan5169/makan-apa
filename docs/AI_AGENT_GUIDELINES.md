# MakanApa? — AI Agent Guidelines

This repository is expected to be developed with AI-assisted editors. These rules exist so a new agent does not destroy useful architectural decisions or re-plan the project from zero every session.

## Mandatory First Action

Before modifying code:

1. Read every Markdown file in `/docs`.
2. Inspect the relevant current code.
3. Summarize your understanding of the requested feature and the files likely to change.
4. Produce a concise plan.
5. Only then implement after approval when the user is operating in a planning workflow.

Do not assume documentation is perfectly current; if code and documentation conflict, report the conflict before making broad architectural changes.

## General Coding Rules

- Follow Laravel conventions unless there is a concrete reason not to.
- Keep controllers thin.
- Put recommendation/business logic in services or appropriately scoped domain classes.
- Prefer Eloquent relationships over repeated manual joins when the relationship is part of the domain.
- Use Laravel validation for external/user input.
- Avoid duplicated queries and obvious N+1 query problems.
- Use explicit, readable names rather than clever abstractions.
- Do not introduce a package when a small amount of clear application code is sufficient.
- Do not rewrite working code merely to match a personal style preference.
- Do not perform unrelated refactors while implementing a focused feature.
- Preserve backward behavior unless the task explicitly changes it.

## Docker Rules

Docker is the project runtime.

Do not instruct the user to install project PHP, Composer, Node.js, MySQL, or Redis directly on the host unless the task specifically concerns a non-Docker alternative.

Prefer commands through Docker Compose.

Do not remove the named-volume strategy for `vendor` or `node_modules` without understanding the Docker Desktop filesystem-performance reason documented in `CURRENT_STATE.md`.

Do not change language/runtime/database major versions casually.

## Database Rules

- Schema changes must use migrations.
- Do not store raw MySQL data in the repository.
- Keep accepted meals and persistent rejections as durable MySQL facts.
- Keep temporary current-roulette rejection state ephemeral unless a feature explicitly changes that behavior.
- When adding user identity later, plan migration from string-based `person_name` carefully rather than silently breaking historical data.

## Recommendation Engine Rules

The current system intentionally uses understandable weighted randomness.

Do not replace it with AI/ML merely because the product is recommendation-related.

Any new recommendation signal should answer:

1. What user behavior does this represent?
2. Is it temporary or permanent?
3. How does it affect eligibility or weight?
4. Can the effect be explained and tested?
5. Does it accidentally make results deterministic or permanently suppress too many options?

Keep actual random selection after weights are calculated unless a feature explicitly requires deterministic ranking.

## UI Rules

- Application-facing copy should remain professional English unless the product direction changes.
- Maintain the current simple, clean visual direction.
- Avoid turning the main decision screen into a dashboard full of controls.
- Mobile usability should not be sacrificed.
- Preserve accessibility basics: labels, button semantics, readable contrast, and clear validation messages.

## Scope Discipline

If asked to implement Feature A, do not also implement future Feature B just because it appears in `ROADMAP.md`.

The roadmap is intentionally split across multiple machines/development phases for learning purposes.

When useful, point out a future improvement, but leave it unimplemented unless requested.

## Expected Completion Report

After implementing a feature, report concisely:

- what changed;
- which files changed;
- migrations or commands required;
- how the change was verified;
- any remaining limitation or follow-up item.
