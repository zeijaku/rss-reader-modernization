# SB-00 to SB-02 Test Report

Date: 2026-07-29 JST

## Executed successfully

- PHP syntax lint: 9 PHP files, all passed.
- PHP runtime/static checks: 10 passed.
- Python structural/security checks: 24 passed.
- Independent SQLite SQL-behavior test: passed.
- Secret-pattern scan over `public/` and `app/`: passed.

Tested concerns include:

- No known dump/log/session/database/archive files under `public/`.
- Common PHP and configuration remain outside DocumentRoot.
- Environment-variable configuration is present and copied Legacy secrets are absent.
- Browser database exception disclosure via `var_dump` was removed.
- A generic production exception boundary exists.
- PDO exception mode, associative fetch mode, native prepares and MySQL `utf8mb4` are configured.
- User + configuration creation uses a transaction with rollback behavior.
- INSERT functions use prepared statements.
- SQL-like payloads remain data and do not alter table structure in the independent behavior test.
- Feed and Stock ordering are deterministic.
- The `H:m:s` timestamp bug is removed from the DB layer.
- Legacy evidence hash and tree manifests exist.

## Environment limitation

The execution container has PDO core but no `pdo_mysql` or `pdo_sqlite` PHP driver. Therefore PHP-to-database integration could not be executed here. The PHP integration test is included and will run automatically when `pdo_sqlite` is available. Production MySQL verification remains required using `CHECKLIST_FOR_USER.md`.

This limitation does not mean the MySQL integration passed; it is explicitly unverified in this environment.

## Revision 2 regression checks

The deployment hotfix was additionally checked for:

- Private `config/local.php` fallback support for shared hosting.
- Git exclusion of the real private local configuration.
- Browser-safe exception reference IDs with private error logging.
- Fresh unauthenticated GET rendering through PHP's built-in web server.
- Controlled 500 response on a login attempt when no PDO DB driver/configuration exists.
- Private log correlation between the browser reference and the server-side exception.
- Legacy numeric HTTP form values accepted as strings at the DB boundary and normalized to integers before binding.

In the test container, the deliberate login attempt without a PDO driver returned HTTP 500 and the private log identified `PDOException: could not find driver`, demonstrating that the generic browser message is diagnostic masking rather than the root cause itself.


## Revision 3 login UI regression checks

The login-page regression reported during deployment was traced to a Legacy control-flow defect: UI defaults were initialized only on requests that were neither `login` nor `regist`. A failed login could therefore render with an empty `conf_style`, producing `./css/.min.css`. Font Awesome still loaded, while Bootstrap did not; as a result both `.collapse` panels were visible and form/button styling disappeared.

R3 checks performed:

- PHP syntax lint: all PHP files passed.
- Existing R2 PHP checks: 16 passed.
- Existing structural/security checks: 32 passed.
- Independent SQL binding/transaction/order checks: passed.
- R3-specific static regression checks: 10 passed.
- R3 theme/default behavioral checks: passed for empty, invalid/path-like and known theme values.
- Fresh unauthenticated HTTP GET generated `./css/bootstrap.min.css`.
- Bootstrap, Font Awesome, Drawer CSS, jQuery, Popper and Bootstrap JS were individually requested from the test HTTP server and returned HTTP 200.
- A defensive non-Bootstrap collapse rule is present so the registration panel cannot become simultaneously visible merely because Bootstrap CSS fails to load.

The environment still lacks `pdo_mysql`, therefore an end-to-end MySQL authentication test remains a deployment-side check.

## R5 session persistence regression tests

Added after a deployment reproduced "login returns to the login form" with a newly registered user.

- PHP syntax: all package PHP files pass `php -l`.
- Legacy `session.save_path=./session_file` override test: pass.
- Private `var/session/` write/close/reopen round trip: pass.
- `index.php` configures the private save path before `session_start()`: pass.
- `logout.php` configures the private save path before `session_start()`: pass.
- Session runtime files are ignored while `var/session/.gitkeep` is retained: pass.
- HTTP redirect + cookie round trip using PHP built-in server and curl: `SESSION_OK`, HTTP 200.

The HTTP test sets a session value, returns a redirect, sends the PHP session cookie on the redirected request, and confirms the value is restored from `var/session/`.
