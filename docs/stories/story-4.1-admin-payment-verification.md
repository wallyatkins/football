# STORY-4.1: Admin Payment Verification Dashboard

- **Epic**: EPIC-4 (Admin Verification & Standings)
- **Status**: Planned
- **Type**: Feature / Admin

## Description
Create the role-gated `/admin/payments` portal where the commissioner can view all weekly entrants, review their payment status, and toggle entries as `paid` or `exempt` with a single click.

## Acceptance Criteria
1. `/admin` routes restricted to users with `role = 'admin'`.
2. Weekly dashboard lists all entrants with: Username, Submission Time, Pick Count, Tiebreaker Score, and Payment Status (`pending`, `paid`, `exempt`).
3. Single-click AJAX or form POST action toggles payment status.
4. Updates `payment_verified_at` and records `payment_verified_by`.
5. Prompts users in the player UI with commissioner Venmo and Cash App handles until verified.

## Technical Tasks
- [ ] Build `src/Controllers/AdminController.php`.
- [ ] Implement `POST /admin/payments/verify` action.
- [ ] Create `templates/admin/payments.php` table with filtering by week and status.
- [ ] Add configuration for commissioner Venmo (`$handle`) and Cash App (`$cashtag`).
