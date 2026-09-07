# STORY-1.2: WallyAuth OIDC Integration with PKCE Support

- **Epic**: EPIC-1 (Auth & Workspace Baseline)
- **Status**: Planned
- **Type**: Feature / Security

## Description
Implement full OpenID Connect (OIDC) Authorization Code Flow with Proof Key for Code Exchange (PKCE) targeting `https://auth.wallyatkins.com`. Extract claims (`sub`, `email`, `preferred_username`, `roles`) and manage authenticated sessions.

## Acceptance Criteria
1. `GET /auth/login` generates state and PKCE verifier/challenge (`S256`) and redirects to `auth.wallyatkins.com/oauth/authorize`.
2. `GET /auth/callback` validates state parameter, exchanges code for tokens at `/oauth/token` via POST.
3. ID token is parsed and validated; user details upserted in the local database.
4. Users with `admin` role claim in token or matching configured admin emails receive administrative access.
5. `GET /auth/logout` terminates local session and redirects cleanly.

## Technical Tasks
- [ ] Create `WallyAuthClient.php` in `src/Auth/` implementing OAuth 2.0 PKCE.
- [ ] Build `AuthController.php` handling `/auth/login`, `/auth/callback`, and `/auth/logout`.
- [ ] Implement session guard middleware protecting authenticated routes.
- [ ] Write unit test for PKCE code verifier and challenge generation.
