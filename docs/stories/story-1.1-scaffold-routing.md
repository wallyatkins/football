# STORY-1.1: Scaffold Project Structure, Apache Routing & Front Controller

- **Epic**: EPIC-1 (Auth & Workspace Baseline)
- **Status**: Planned
- **Type**: Infrastructure / Backend

## Description
Establish the foundational PHP 8.2+ workspace structure, Composer autoloading, `.htaccess` Apache rewrite rules, and a clean front controller request router that dispatches incoming URIs to controller actions.

## Acceptance Criteria
1. Web server rewrite rules in `public/.htaccess` route all non-file/non-directory requests to `public/index.php`.
2. `public/index.php` initializes sessions, error reporting, autoloader, and instantiates the Router.
3. Clean HTTP responses for `GET /`, `GET /healthz`, and 404 handler for unknown routes.
4. Master layout template (`templates/layout.php`) renders responsive dark-theme shell matching `wallyatkins.com`.

## Technical Tasks
- [ ] Create `composer.json` with PSR-4 autoloading for `WallyFootball\\`.
- [ ] Create `public/.htaccess` with `RewriteEngine On`, HTTPS enforcement, and security headers.
- [ ] Build minimalist `Router.php` in `src/Routing/` supporting GET and POST routes with route parameters.
- [ ] Implement `templates/layout.php` with navigation header, user status badge, and mobile drawer.
