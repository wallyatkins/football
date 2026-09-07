# STORY-2.2: Automated NFL Schedule & Score Ingestion Service

- **Epic**: EPIC-2 (Ingestion & Game Management)
- **Status**: Planned
- **Type**: Backend / Integration

## Description
Develop `SportsDataService` and the CLI sync tool `bin/sync-nfl.php` to fetch NFL regular season weekly schedules, kickoff times, game status, and live/final scores from the ESPN public scoreboard API.

## Acceptance Criteria
1. `SportsDataService::syncWeek(int $seasonYear, int $weekNumber)` fetches and parses games from ESPN API.
2. Accurately maps home/away teams, ISO 8601 kickoff UTC timestamps, scores, and status (`scheduled`, `in_progress`, `final`).
3. Automatically detects Monday Night Football games and flags `is_mnf = TRUE`.
4. Idempotently updates existing game records without overwriting historical game IDs.
5. CLI tool `php bin/sync-nfl.php --season=2026 --week=1` logs sync statistics.

## Technical Tasks
- [ ] Implement `src/Services/SportsDataService.php` using `curl` or stream contexts.
- [ ] Map ESPN team abbreviations to canonical standard team codes.
- [ ] Build `bin/sync-nfl.php` CLI script with options for specific weeks or current active week.
- [ ] Add unit test with mocked ESPN JSON fixture.
