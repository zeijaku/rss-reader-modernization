# Legacy → Secure Baseline Change Map

Legacy解析で付与したissue IDを、Secure Baselineのwork unit、主要file、testへ追跡するためのmapです。

Statusの `Policy` は、単純実装ではなく「安全な方針を明示して解決」したものを示します。

| Legacy issue | SB | Status | Main implementation | Representative verification |
|---|---:|---|---|---|
| SEC-001 API authentication | 05 | Fixed | `app/api.php`, `public/api_v1.php`, `app/session.php` | `test_sb05_07_api.php`, HTTP tests |
| SEC-002 request owner trust | 06 | Fixed | `app/api.php`, `app/common/common_db.php` | `test_sb05_07_api.php` |
| SEC-003 content owner condition | 06 | Fixed | `app/common/common_db.php`, `app/api.php` | IDOR tests |
| SEC-004 settings target user | 06 | Fixed | `app/api.php`, DB helpers | authorization tests |
| SEC-005 CSRF | 07 | Fixed | `app/session.php`, `app/api.php`, auth/logout UI | HTTP/static CSRF tests |
| SEC-006 arbitrary outbound URL / SSRF | 08-09,14 | Fixed | `app/validation.php`, `app/http_fetch.php` | SB-09 + SB-14 SSRF matrix |
| SEC-007 redirect revalidation | 09 | Fixed | `app/http_fetch.php` | fetch redirect tests |
| SEC-008 TLS verification disabled | 09 | Fixed | `app/http_fetch.php` | static TLS invariants |
| SEC-009 Feed XSS | 10 | Fixed | `app/api.php`, `public/index.php`, validation helpers | XSS matrix |
| SEC-010 DB/settings XSS | 10 | Fixed | `app/validation.php`, `public/index.php` | output/static tests |
| SEC-011 SQL string concatenation | 02+ | Fixed | `app/common/common_db.php`, auth/data layer | SQL/static/behavior tests |
| SEC-012 Session fixation | 03 | Fixed | `app/session.php` | Session tests |
| SEC-013 Legacy password scheme | 04 | Fixed/Policy | `app/auth.php` | auth tests |
| SEC-014 Session cookie/lifetime | 03 | Fixed | `app/session.php` | HTTP/session tests |
| SEC-015 secret config exposure | 01 | Fixed | `config/`, `app/common/common_conf.php`, `.gitignore` | repository scan |
| SEC-016 production DB dump publish risk | 01,13,14 | Fixed | `.gitignore`, sanitized `database/` | repository scan |
| SEC-017 Legacy logs publish risk | 01,14 | Fixed | private `var/`, `.gitignore` | repository scan |
| SEC-018 Web-root Session storage | 01,03 | Fixed | `app/session_storage.php`, `var/session/` | session layout tests |
| SEC-019 DB error disclosure | 01-02 | Fixed | `app/bootstrap.php`, DB boundary | public warning/error tests |
| AUTH-001 disabled user login | 04 | Fixed | `app/auth.php`, DB lookup | auth tests |
| AUTH-002 duplicate registration identity | 04 | Fixed | auth/registration path | auth tests |
| AUTH-003 Legacy credential migration | 04 | Policy | unknown Legacy format fails closed; no auto migration | auth tests |
| AUTH-004 login rate limit | 04 | Fixed | `app/login_throttle.php` | throttle tests |
| API-001 API Session bootstrap | 05 | Fixed | API/bootstrap/session | API HTTP tests |
| API-002 fixed token | 07 | Fixed | Session CSRF | CSRF tests |
| API-003 implicit action routing | 05 | Fixed | `app/api.php` dispatcher | API tests |
| API-004 inconsistent JSON | 05 | Fixed | `app/api.php` | API tests |
| API-005 API 302/HTML | 05 | Fixed | API boundary | HTTP tests |
| BUG-001 `H:m:s` | 02 | Fixed | DB date creation | regression/static tests |
| BUG-002 tab 0/2/3/3 | 11 | Fixed | validation/dashboard mapping | 4-tab tests |
| BUG-003 Feed assignment branch | 11 | Fixed | parser/type logic | SB-11/12 tests |
| BUG-004 fetch failure as text success | 11 | Fixed | parser/fetch error handling | parser/fetch tests |
| BUG-005 fixed 5-item loop | 11 | Fixed | dashboard rendering | fixture/static tests |
| BUG-006 weak tab validation | 08 | Fixed | `app/validation.php` | validation tests |
| BUG-007 invalid tab undefined path | 11-12 | Fixed | runtime validation/fallback | runtime/static tests |
| BUG-008 unclosed final row | 11 | Fixed | dashboard HTML | static tests |
| BUG-009 missing Stock title | 09-11 | Fixed | Stock no longer re-fetches article page; validation/fallback | Stock/API tests |
| BUG-010 missing User-Agent | 09,12 | Fixed | safe outbound/default handling | fetch/runtime tests |
| VALID-001 URL sanitize misuse | 08 | Fixed | URL validators | validation tests |
| VALID-002 enum validation | 08 | Fixed | centralized allowlists | validation tests |
| VALID-003 DB length validation | 08 | Fixed | centralized validators | validation tests |
| FETCH-001 cURL/HTTP result | 09 | Fixed | `app/http_fetch.php` | fetch tests |
| FETCH-002 response size | 09 | Fixed | streaming size limit | fetch tests |
| FETCH-003 timeout policy | 09 | Fixed | configurable connect/total timeout | fetch tests |
| RSS-001 Feed structural detection | 11-12 | Fixed for Baseline | parser logic | parser fixtures/static tests |
| RSS-002 XML parse error | 09,11-12 | Fixed | `LIBXML_NONET`, structured failure | parser tests |
| RSS-003 invalid/missing date | 11-12 | Fixed | parser normalization | parser matrix |
| RSS-004 Atom alternate link | 12 R2 | Fixed | `app/common/common_func.php` | Atom fixture/link tests |
| DATA-001 registration transaction | 02,14 | Fixed | DB transaction | rollback test |
| DATA-002 FK decision | 13 | Policy | no automatic FK at Baseline | schema tests |
| DATA-003 duplicate identity | 04,13 | Policy/Guard | fail closed + audit, no destructive cleanup | auth/integrity tests |
| DATA-004 owner/flag/location indexes | 13 | Fixed | `database/schema.sql` | schema tests |
| DATA-005 utf8mb4 | 02,13 | Fixed | PDO DSN + schema | schema/static tests |
| DATA-006 PDO attributes | 02 | Fixed | DB connection | regression/static tests |
| OPS-001 public schema/fixture | 13 | Fixed | `database/` | repository/schema tests |
| OPS-002 Legacy MySQL 5.x path | 13 | Resolved by new-install path | MySQL 8 new DB + schema; migration path separated | deployment verification |

## Notes

- RSS Engineそのもののcache/conditional request/source modelはSecure Baseline後へ分離しています。
- Frontend dependency更新もSecure Baseline後です。
- Change mapの「Fixed for Baseline」は、今後のEngine再設計余地を残しながら、現在の安全/機能要件を満たしたことを意味します。
