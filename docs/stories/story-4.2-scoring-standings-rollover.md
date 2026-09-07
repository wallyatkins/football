# STORY-4.2: Scoring Calculation, Standings Table & Pot Rollover

- **Epic**: EPIC-4 (Admin Verification & Standings)
- **Status**: Planned
- **Type**: Feature / Analytics

## Description
Implement the automated scoring engine that grades completed games, tallies weekly Pick'em points, evaluates the MNF tiebreaker delta, resolves ties, calculates the prize pot, and manages carryover pools.

## Acceptance Criteria
1. Correct picks scored as 1 point; incorrect as 0.
2. Standings leaderboard ranks players by: 1) Total Correct Picks (descending), 2) MNF Tiebreaker Delta $|\text{Pred} - \text{Actual}|$ (ascending).
3. Unverified / unpaid entries are excluded from official standings and prize calculations.
4. Total weekly pot computed as $\text{verified\_entries} \times \text{entry\_stake}$.
5. In the event of an unbreakable tie, splits pot equally or flags rollover to the subsequent week based on league configuration.
6. Season-long aggregate standings view summing weekly scores.

## Technical Tasks
- [ ] Build `src/Services/ScoringEngine.php` with comprehensive tiebreaker math.
- [ ] Create `templates/pickem/standings.php` with highlighting for winner(s).
- [ ] Write unit tests in `tests/Unit/ScoringEngineTest.php` covering exact tiebreaker edge cases and split pot calculation.
