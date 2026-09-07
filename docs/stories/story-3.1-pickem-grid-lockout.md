# STORY-3.1: Pick'em Matchup Grid, Submission & Kickoff Lockout

- **Epic**: EPIC-3 (Gameplay & Submission State Engine)
- **Status**: Planned
- **Type**: Feature / Frontend & Backend

## Description
Build the weekly Pick'em view where participants make straight-up winner selections across all games for the week, input a tiebreaker total score for the MNF game, and submit their picks. Enforce per-game kickoff lockout.

## Acceptance Criteria
1. Displays all matchups for the selected week with team logos, kickoff times, records, and odds/venues if available.
2. Clicking a team selects it as the straight-up winner.
3. Requires integer tiebreaker score prediction for Monday Night Football before submission.
4. On submission, backend validates that `CURRENT_TIMESTAMP < game.kickoff_time` for each submitted pick; rejected if game already kicked off.
5. Games that have already started are disabled in the UI with a lock icon.
6. Opponents' picks for unstarted games remain hidden; unlocked and visible once a game starts.

## Technical Tasks
- [ ] Build `src/Controllers/PickemController.php` (render week slate, handle save).
- [ ] Create `templates/pickem/grid.php` with responsive cards for each matchup.
- [ ] Implement lockout check in `PickemController::savePicks()`.
- [ ] Display payment reminder banner when entry status is `pending`.
