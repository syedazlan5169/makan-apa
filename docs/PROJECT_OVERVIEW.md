# MakanApa? — Project Overview

## Purpose

MakanApa? is a small office meal recommendation application created to solve a real problem: everyone orders from the same restaurant, the restaurant has a large menu, and choosing what to eat becomes repetitive and annoying.

The application should make the decision quickly while gradually learning from each person's previous choices and rejections.

The product should feel like a useful professional internal application, not a demo project.

## Core Product Idea

A user:

1. Enters or selects their identity.
2. Optionally selects a food category.
3. Clicks **Choose for me**.
4. Receives one recommendation.
5. Either accepts it or clicks **Pick something else**.
6. Rejected items are not shown again during the same decision session.
7. Accepted meals and historical rejections influence future recommendations.

## Product Philosophy

- Keep the main interaction extremely fast.
- Avoid adding complexity that does not improve the lunch-selection experience.
- Prefer understandable recommendation rules over unnecessary machine learning.
- Randomness is intentional; the application should recommend, not deterministically dictate.
- Historical behavior should influence probability, not permanently ban meals unless a future feature explicitly does so.
- Maintain a professional English UI.
- Mobile-friendly design is desirable because users may open the app from a phone later.

## Current Scope

The Office-PC development phase delivered the first working vertical slice:

- Laravel application running fully through Docker.
- Menu categories and menu items.
- Random/weighted meal recommendations.
- Optional category filter.
- Persistent accepted-meal history.
- Temporary rejection memory during the current roulette session using Redis-backed sessions.
- Persistent rejection history in MySQL.
- Recency-based recommendation weighting.
- Rejection-based recommendation weighting.
- Recent office choices shown on the home page.
- Professional English UI.

## Current Non-Goals

The following are intentionally not completed yet so later development phases still have meaningful work:

- Full authentication.
- Proper employee/user profiles.
- Favorites and ratings.
- Advanced preference filters such as budget, spicy level, meal type, etc.
- Admin CRUD for maintaining the real restaurant menu.
- Statistics/analytics dashboard.
- Production deployment architecture.
- Automated tests with meaningful coverage.

## Development Journey

This project is also being used as a practical Docker learning project.

Development is intentionally split across machines:

- **Office Windows PC** — Docker fundamentals and first working application.
- **MacBook** — continue application features after cloning the same repository into a fresh Docker environment.
- **Home Windows PC** — continue another development phase from a clean clone.
- **VPS** — later learn proper production containerization and deployment.

The important learning goal is that no machine should require a manually installed project-specific PHP, Composer, Node.js, MySQL, or Redis runtime. Docker should provide the project environment.
