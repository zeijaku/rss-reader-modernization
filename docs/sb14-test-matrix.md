# SB-14 Final Test Matrix

Build: `Secure Baseline SB-14 / R1`

SB-14 is the release-blocking verification stage for the Secure Baseline. It does not add product features. It cross-checks SB-00 through SB-13 and closes defects discovered by the expanded matrix.

## Policy note: Legacy password compatibility

The original planning draft contained login tests for automatic migration of a Legacy 64-hex credential. The project policy was later changed: compatibility with existing Legacy users is not required. The current secure behavior is therefore:

- current `password_hash()` credentials authenticate;
- hashes that need rehash are upgraded after a successful login;
- unknown/Legacy credential formats fail closed;
- duplicate active identities fail closed as ambiguous.

SB-14 verifies the current policy rather than reintroducing Legacy credential compatibility.

## Matrix

| Area | Required behavior | Coverage | Result |
|---|---|---|---|
| Authentication | current hash login, invalid password, disabled user, unknown Legacy format, duplicate identity, session-id rotation | `test_sb04_auth.php`, `test_sb03_session.php`, HTTP session tests | PASS |
| Registration | normal register, duplicate identity, invalid email/password, disabled registration, atomic rollback | existing SB-04 tests + `test_sb14_auth_rollback.php` | PASS |
| Authorization / IDOR | User A cannot mutate/fetch User B resources; request owner fields ignored | `test_sb05_07_api.php` | PASS |
| CSRF | login/register/API/logout; missing, wrong, valid | HTTP tests + `test_sb14_surface_static.py` | PASS |
| SSRF | loopback/private/link-local/ULA/reserved/special-use/non-default port/userinfo/redirect defense | SB-09 tests + `test_sb14_ssrf_matrix.php` | PASS after SB-14 hardening |
| Fetch failures | DNS failure, timeout, HTTP 404/500, empty, oversized, redirect limit | SB-09 + SB-14 tests | PASS |
| TLS policy | automatic redirects off, peer verification on, hostname verification on, DNS pinning | static invariants | PASS |
| XSS | Feed, Stock, tabs, navbar, content URL and output boundary | SB-10 tests + `test_sb14_xss_matrix.php` | PASS |
| Parser | RSS 2.0, RSS 1.0, Atom, no XML declaration, zero/1-4/5+ items, invalid/missing date, alternate link, malformed XML | fixtures + conditional PHP parser tests | PASS where executable; live SimpleXML cases require deployment runtime |
| 4-tab regression | locations 0/1/2/3 map to tabs 1/2/3/4; Stock explicit; invalid -> safe default | validation + static dashboard tests | PASS |
| DB integrity | target schema, indexes, prefix, non-destructive migration, no FK cleanup surprise | SB-13 suite | PASS |
| Repository leak scan | no local config, real env, dump, logs, sessions, backups, obvious key material | `test_sb14_repository_scan.py` | PASS |
| PHP 8 runtime | lint, PHP 8.1 floor, warning/deprecation smoke, error-policy checks | SB-12 suite + full lint | PASS |
| Package reproducibility | clean runtime dirs, ZIP integrity, re-extract and rerun suite | release packaging procedure | Required before distribution |

## SB-14 defect found and fixed

The expanded SSRF matrix found that PHP's `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` does not classify every IANA special-use range as non-public on the test runtime. Examples included documentation, benchmark, shared-address, and multicast IPv4 ranges.

SB-14 therefore adds an explicit CIDR deny list after the built-in IP validation. This is defense-in-depth and intentionally fails closed for special-use destinations.

The parser/link path was also rechecked as part of the static matrix; no parser code change was required in SB-14.

## Environment-limited cases

The build environment does not provide `pdo_mysql`, cURL, SimpleXML, or mbstring. Therefore these cannot be claimed as locally executed end-to-end tests:

- real MySQL 8 queries from PHP/PDO;
- real cURL/TLS network handshakes;
- real SimpleXML parsing of the full parser matrix.

The code paths are covered by fakes/static invariants and deployment-side verification remains the final gate for those environment-dependent paths.
