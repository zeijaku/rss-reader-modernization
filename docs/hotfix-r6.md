# R6 Hotfix — Logout route and session termination

## Symptom

Selecting Logout returned HTTP 404. A subsequent visit still showed the authenticated RSS dashboard.

## Root cause

`public/index.php` linked to `./logout`, but the deployed endpoint is `public/logout.php`. The Secure Baseline `.htaccess` intentionally does not provide extensionless PHP rewrites, so the request never reached the logout handler.

## Changes

- Changed the logout link to `./logout.php`.
- Clear the complete `$_SESSION` array before destroying the session.
- Delete the session cookie with the current session cookie path/domain/security attributes.
- Redirect to `./` with HTTP 303 after logout.
- Added regression tests covering the route and logout implementation.

## Expected behavior

Logout -> `logout.php` -> session and cookie cleared -> redirect to login screen. Reloading the application must remain logged out.
