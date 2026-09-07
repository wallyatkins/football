# STORY-2.1: Dual-Driver Database Schema & Migration Runner

- **Epic**: EPIC-2 (Ingestion & Game Management)
- **Status**: Planned
- **Type**: Backend / Database

## Description
Implement the database connection manager and automated migration runner supporting both PostgreSQL 15+ (production) and SQLite 3 (local/testing). Create tables for users, games, pick'em entries, pick'em picks, and survivor picks.

## Acceptance Criteria
1. `src/Database/Connection.php` creates PDO instance based on environment configuration (`DB_DRIVER=pgsql|sqlite`).
2. `bin/migrate.php` executes initial DDL schema migrations idempotently.
3. Enforces primary keys, unique constraints, and foreign key cascades.
4. Unit tests pass with an in-memory SQLite database.

## Technical Tasks
- [ ] Write `src/Database/Connection.php` with transaction support.
- [ ] Create migration SQL file `db/migrations/001_initial_schema.sql`.
- [ ] Build `bin/migrate.php` CLI runner.
- [ ] Verify foreign key constraints and unique indexes on `pickem_picks` and `survivor_picks`.
