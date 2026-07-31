# R5 hotfix — login succeeds but session disappears after redirect

## Symptom
A newly registered account can be submitted at Sign in, but the page returns to the login form instead of showing the RSS dashboard.

## Root cause
The Legacy package configured PHP sessions in its root `.htaccess`:

```apache
php_value session.save_path "./session_file"
```

SB-01 intentionally removed the web-accessible `session_file/` directory. When the Secure Baseline package is deployed over an existing Legacy directory, the old parent `.htaccess` may remain and be inherited by `public/`. Authentication can then succeed in the POST request, but PHP cannot persist/restore the session across the redirect, so `$_SESSION['user_id']` disappears and the application renders the login screen again.

## Changes
- Added `app/session_storage.php`.
- `index.php` and `logout.php` now override any inherited Legacy `session.save_path` before `session_start()`.
- Session files are stored in `var/session/`, outside DocumentRoot.
- Added an application-root `.htaccess` without the Legacy `session.save_path` directive so in-place deployments overwrite the old file when hidden files are extracted.
- Added a session write/close/reopen round-trip test.
- Healthcheck reports the private session path and writability.

## Scope
This is only the storage-location fix needed for SB-00–02 operation. Cookie security, ID regeneration, fixation defenses, inactivity policy, and minimal session contents remain SB-03 work.
