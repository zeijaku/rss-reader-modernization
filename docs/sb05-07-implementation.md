# SB-05〜SB-07 Implementation Notes

## Basis

Implemented on top of the user-verified `Secure Baseline SB-04 / R2` checkpoint.
The design follows the previously fixed Secure Baseline decomposition.

## SB-05 — API Contract / Dispatcher

Implemented:

- `public/api_v1.php` reduced to Web/API boundary responsibilities
- new private `app/api.php` explicit dispatcher
- POST-only API
- explicit `action` required
- unified success/error JSON structures
- HTTP status aligned with error class
- API redirects removed
- API-side unexpected exceptions converted to generic JSON 500 with private correlation logging
- browser calls consolidated through a common `apiRequest()` JavaScript helper

Actions:

- `content.create`
- `content.update`
- `content.delete`
- `stock.create`
- `settings.update`
- `tabs.update`
- `feed.fetch`

## SB-06 — Authorization / Ownership

Implemented:

- authenticated Session `user_id` is the only owner source
- request `content_owner`, `save_owner`, `user_id` targeting removed from browser/API contract
- content update SQL scopes by `content_id + content_owner + active flag`
- content delete SQL scopes by `content_id + content_owner + active flag`
- owner-aware active content lookup added
- settings/tab updates always target authenticated user configuration
- Stock owner always set to authenticated user
- `feed.fetch` accepts only `content_id`
- Feed URL is retrieved server-side from an active row owned by the authenticated user
- unauthorized/not-owned resources return not-found behavior rather than revealing ownership

## SB-07 — CSRF

Implemented:

- existing cryptographic Session CSRF helper retained
- Login form token
- Registration form token
- login/register token verification before authentication/DB registration
- all API requests validate CSRF centrally before action dispatch
- `feed.fetch` requires CSRF because it causes server-side outbound activity
- Logout remains POST + CSRF

## Deliberately deferred

SB-05〜07 establish request identity and ownership boundaries but do not complete untrusted-data security.

Deferred:

- SB-08 strict field validation / enums / lengths / URL policy
- SB-09 SSRF, redirect/DNS/IP validation, TLS verification, Stock title-fetch removal
- SB-10 XSS-safe output and DOM construction
- SB-11 parser/UI functional bugs, including the fixed-five-item rendering behavior

Therefore this remains a development checkpoint, not the final production/GitHub Secure Baseline.
