# SB-08〜SB-10 Implementation Notes

Build: `Secure Baseline SB-10 / R1`
Base: user-verified `Secure Baseline SB-07 / R1`

## Scope

This checkpoint implements the input/output and outbound-request security boundaries defined by the Secure Baseline decomposition.
It deliberately does not redesign Legacy functional behavior assigned to SB-11.

## SB-08 — Strict Validation

Added private `app/validation.php` and centralized validators for:

- strict positive integer resource IDs
- exact content location 0..3
- exact tab query 0..3 or `stock`
- content style allowlist
- theme allowlist
- navbar style allowlist
- navbar icon allowlist with controlled Legacy `fa-` normalization
- UTF-8 text/control-character/length limits
- Feed URL / Stock URL / Navbar URL policies
- render-time Legacy UI configuration normalization/fallback
- context-safe HTML text/attribute escaping helper

URL policy prevents non-http schemes and userinfo from crossing write/output boundaries. Feed URL fragments are rejected by fixed policy.

## SB-09 — Safe outbound Feed fetch

Added private `app/http_fetch.php`.

### Target policy

For every initial or redirected Feed request:

1. Normalize/parse the URL.
2. Allow only HTTP/HTTPS.
3. Require standard port 80/443 in the baseline.
4. Resolve A/AAAA records.
5. Reject loopback/private/link-local/reserved addresses.
6. Fail closed when any usable DNS answer is non-public.
7. Pin a validated DNS address into the cURL connection for hostname URLs.
8. Preserve original URL hostname for HTTP Host, TLS SNI and certificate hostname verification.

Validated public IP-literal URLs are already bound to the literal address and do not need `CURLOPT_RESOLVE`.

### HTTP/TLS policy

- cURL automatic redirect following disabled
- redirects followed manually, default maximum 3
- every redirect target is fully revalidated
- TLS peer verification enabled
- TLS hostname verification enabled
- fixed application User-Agent
- connect timeout and total timeout
- body size limited during streaming write
- only successful 2xx non-empty response accepted

### Stock fetch removal

Legacy Stock creation fetched the article URL server-side to extract its page title. That second outbound request has been removed.
The Feed UI sends the already-displayed article title and validated URL to `stock.create`, and the API validates/bounds both before storage.

## XML parser network boundary

`rss_parse::parse_start()` no longer hides XML parser errors with `@` and invokes SimpleXML with `LIBXML_NONET`.
This prevents XML parsing from becoming a secondary network-fetch path.

## SB-10 — XSS-safe output

### Server output

- `app_html()` uses `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE, UTF-8)`.
- DB/UI style/theme/icon values are normalized before use in class/path contexts.
- URLs are validated before external `href` output.
- `_blank` links receive `rel="noopener noreferrer"`.
- Stock titles/dates/URLs and UI labels are escaped at output.

### Feed API payload

Feed data is transformed into bounded plain text + validated URLs before JSON response.
Markup in title/description/content/date is stripped rather than returned as trusted HTML.
Unsafe item links are omitted; an unsafe channel link falls back to the validated effective Feed URL.

JSON response applies `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`, and `JSON_HEX_QUOT` as defense in depth.

### Browser rendering

Legacy untrusted HTML string concatenation was replaced by element construction using jQuery `.text()` and `.attr()`.
Feed row rendering also bounds iteration to the items actually present, avoiding blind five-element dereference as part of the safe renderer work.

## Deliberately deferred to SB-11+

- Legacy four-tab navigation mapping defect
- parser semantics / legacy text fallback decisions
- broader UI behavior cleanup
- other pre-existing functional defects listed in the Legacy analysis
- final PHP 8 cleanup, schema/data integrity, final matrix/docs gate

The SB-10 checkpoint is therefore materially safer but is still not the final GitHub/production Secure Baseline gate.
