# STORY-3.2: Survivor Pool Team Selection & Exclusivity Engine

- **Epic**: EPIC-3 (Gameplay & Submission State Engine)
- **Status**: Planned
- **Type**: Feature / Frontend & Backend

## Description
Build the Survivor mode view allowing an entrant to select exactly one team to win each week, strictly enforcing season-wide exclusivity (a team cannot be selected more than once per season by the same player) and elimination tracking.

## Acceptance Criteria
1. Displays the weekly schedule with eligible teams.
2. Teams chosen by the participant in prior weeks are visually crossed out and disabled.
3. Prevents saving a pick if the participant is already marked `is_eliminated = TRUE`.
4. Enforces kickoff lockout: the pick must be submitted prior to that specific team's game kickoff.
5. If the chosen team loses or ties in a final game, the entry is marked `is_eliminated = TRUE`.

## Technical Tasks
- [ ] Build `src/Controllers/SurvivorController.php`.
- [ ] Query participant's previous season picks: `SELECT selected_team FROM survivor_picks WHERE user_id = :uid AND season_year = :year`.
- [ ] Create `templates/survivor/index.php` showing season pick history, available teams, and active status badge.
- [ ] Add elimination evaluation logic to `ScoringEngine`.
