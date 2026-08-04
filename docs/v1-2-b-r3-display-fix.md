# V1.2-B / R3 Display Fix

## Scope

Article row display only. Feed API, database, cache, authentication, refresh behavior, Stock behavior and `.htaccess` are unchanged.

## Changes

- Article titles now occupy one line when short and up to two lines when long.
- The full title remains in the DOM; only the visible display is clamped.
- Overflow detection now checks both width and height so the existing full-title tooltip still applies only when truncated.
- Stock and NEW controls remain on the left and align naturally with one-line and two-line titles.
- The summary control remains a 44px touch target on the right.
- The summary indicator changed from Unicode `▽` to Font Awesome `fa-plus-square`.
- While expanded, it changes to `fa-minus-square`.

## No changes

- DB / SQL: none
- `config/local.php`: none
- `.htaccess`: none
- API action or response: none
- Feed cache: no deletion required
- Application version: remains `1.2.0-dev.2`
