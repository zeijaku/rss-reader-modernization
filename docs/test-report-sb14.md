# SB-14 Final Test Report

Build: `Secure Baseline SB-14 / R1`

## Scope

SB-14 re-runs the complete Secure Baseline regression suite and adds release-blocking tests for authentication atomicity, special-use SSRF ranges, XSS boundaries, parser fixtures, CSRF surfaces, 4-tab mapping, and repository leak detection.

## Result before packaging

- PHP syntax: all PHP files passed lint
- PASS assertions: 737
- FAIL assertions: 0
- SKIP notices: 3

The three skips are environment limitations:

1. PDO SQLite integration test: no PDO SQLite driver in this environment.
2. SB-12 live SimpleXML fixture parse: SimpleXML/mbstring unavailable.
3. SB-14 live parser matrix: SimpleXML/mbstring unavailable.

The runtime is PHP 8.4.x, so the suite also exercises the code on a PHP version newer than the application's PHP 8.1 minimum.

## New SB-14 tests

### Registration transaction atomicity

`test_sb14_auth_rollback.php` uses a transactional fake PDO to verify that a failed `user_conf` insert rolls back the preceding user insert, and that the successful path commits both rows.

### Expanded SSRF matrix

`test_sb14_ssrf_matrix.php` checks loopback, RFC1918, shared address space, link-local/cloud metadata, TEST-NET, benchmark, multicast, reserved IPv4, IPv4-mapped IPv6, NAT64 special prefixes, documentation IPv6, ULA, link-local and multicast IPv6. It also verifies HTTP 500 handling and a redirect to metadata/link-local space.

### XSS matrix

`test_sb14_xss_matrix.php` covers Feed text/URL, Stock title/URL, tab name, navbar label/URL and generic HTML output escaping.

### Parser matrix

New fixtures cover:

- RSS 2.0 with zero items;
- RSS 2.0 with four items;
- RSS 2.0 with six items;
- Atom without XML declaration and with alternate/self links;
- RSS 1.0 with Dublin Core date;
- malformed XML;
- valid, invalid, and missing dates.

Fixture shapes are independently validated even when PHP SimpleXML is unavailable. Live parser assertions execute automatically when SimpleXML and mbstring are available.

### Repository leak scan

The final scan checks that the package contains no `config/local.php`, real `.env`, Legacy database ZIP/dump, runtime session/log/throttle/migration files, database dump files outside the curated `database/` artifacts, or high-signal private-key/cloud-key patterns.

## Defect discovered by SB-14

The initial expanded SSRF test failed because the PHP built-in private/reserved-address filter accepted several special-use IPv4 ranges on this runtime, including TEST-NET, benchmarking, shared-address and multicast examples.

`app/http_fetch.php` was hardened with explicit CIDR checks. The expanded matrix then passed.

The Atom-link parser path was rechecked and did not require a runtime change in SB-14.

## Packaging gate

Before distribution the release must additionally pass:

1. runtime artifact cleanup;
2. repository leak scan;
3. ZIP integrity verification;
4. extraction into a separate directory;
5. full `tests/run.sh` rerun from the extracted ZIP;
6. SHA-256 generation.

The final distributed report should record the extracted-ZIP result as well.

## Candidate ZIP verification

A candidate ZIP was created, tested with `unzip -t`, extracted into a separate directory, and the full suite was executed from that extracted copy.

- PASS assertions: 737
- FAIL assertions: 0
- SKIP notices: 3 (same environment limitations listed above)
- ZIP integrity: no compressed-data errors

The final distribution ZIP is regenerated after documentation/manifest finalization and is re-verified separately before delivery.
