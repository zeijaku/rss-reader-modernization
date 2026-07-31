# R4 hotfix — silent login failure

## Symptom
Submitting the login form returns to the same page and appears to do nothing.

## Root cause
`search_auth_user()` uses PDO `fetchAll()`. When authentication does not match a row,
`fetchAll()` returns an empty array (`[]`). R3 displayed the login error only when the
result was strictly `false`, so a normal failed authentication produced no visible message.

A separate expected behavior also matters during SB-00–02 testing: Legacy accounts were
hashed using the Legacy `APP_HASH_KEY`. If the key is replaced, those existing rows cannot
be authenticated with the current Legacy HMAC function. Current-user compatibility was
explicitly excluded from this phase, so functional testing must use an account registered
under the current key.

## Changes
- Added `auth_result_is_success()` and use it consistently for login/registration decisions.
- Login failure now renders an explicit error for an empty PDO result.
- Registration success and duplicate-combination results are visible after redirect.
- `view_login()` supports safe Bootstrap alert variants.
- Runtime health check now reports a missing/short `APP_HASH_KEY` (<32 chars).

## Scope
This does not modernize password storage. Password migration remains SB-04.
