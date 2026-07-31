# Test Report — SB-03 / SB-04

## Environment

- PHP CLI: 8.4.16
- PHP built-in HTTP server used for Session/cookie/logout round-trip tests
- PDO core available
- `pdo_mysql` unavailable in the build sandbox
- `pdo_sqlite` unavailable in the build sandbox

Therefore the PHP application cannot perform a live MySQL/MariaDB integration round trip in this sandbox. DB repository/auth behavior was exercised with a PDO-compatible fake and SQL behavior was independently checked with Python SQLite. Real MySQL confirmation remains a deployment-side test.

## Automated coverage

`tests/run.sh` verifies:

### Regression foundation

- PHP syntax for all PHP project/test files
- SB-00/SB-02 static/policy checks
- public/private boundary
- secret-pattern scan
- PDO configuration and prepared-statement policy
- transaction/binding/ordering SQL behavior

### SB-03

- strict Session mode
- cookie-only sessions
- trans-sid disabled
- browser-session lifetime
- HttpOnly
- SameSite=Lax
- Secure=true when HTTPS is detected
- 256-bit CSRF token
- login Session ID regeneration
- minimal Session key set
- idle expiry
- absolute expiry
- Session ID rotation on expiry
- Session persistence across real HTTP requests
- GET logout -> 405
- wrong/missing logout CSRF -> rejected
- invalid logout leaves authentication intact
- valid logout -> 303
- logout cookie expiry
- destroyed old Session ID cannot restore authentication
- private Session path outside DocumentRoot

### SB-04

- keyed normalized identity generation
- case-normalized email identity
- email validation
- registration password bounds
- password_hash storage
- raw password not stored
- raw email not stored in login identity field
- password_verify login
- duplicate identity rejection
- disabled user rejection
- unknown user rejection
- Legacy/non-password_hash credential rejection
- password_needs_rehash path and update
- duplicate active identity fail-closed behavior
- temporary login throttle
- throttle expiry
- successful login clears pair bucket
- throttle storage outside DocumentRoot and guarded by file lock

## Result

Final full-suite result: **160 PASS checks / 0 FAIL**, plus PHP syntax validation for **21 PHP files**. The PDO-SQLite branch was skipped because the sandbox has no SQLite PDO driver; its SQL semantics are covered by the independent Python SQLite test and deployment-side MySQL verification remains required.

## Deployment-side confirmation still required

- real pdo_mysql connection
- register -> MySQL row -> login round trip
- real hosting HTTPS cookie has Secure attribute
- filesystem ownership/permission for `var/session/` and `var/security/login-throttle/`
- regression of Feed/Stock/settings functionality against the target MySQL database

## R2 Visible Version Marker regression

- `app/version.php` defines the distributed release marker.
- Login/registration views render `Secure Baseline SB-04 / R2`.
- Authenticated view contains the same marker in the footer.
- Public HTTP smoke test verifies the marker in rendered login HTML.
- Static tests verify both login and authenticated insertion points.

