# Hotfix R3 — Login UI regression

## Symptom

After a login attempt, Bootstrap styling disappears and both the login and registration forms are displayed at the same time.

## Root cause

Legacy `index.php` initialized `$_SESSION['conf_style']` only in the branch used when the request was neither `login` nor `regist`.

When login authentication failed, execution continued to rendering without initializing `conf_style`. The generated stylesheet URL therefore became:

```text
./css/.min.css
```

Font Awesome still loaded from `./css/all.css`, which explains why the RSS icon remained visible while Bootstrap classes such as `.collapse`, `.btn`, and `.form-control` stopped working.

The compatibility policy for existing accounts makes a failed Legacy login more likely, exposing this pre-existing control-flow defect.

## Fix

- Initialize UI session defaults before authentication branching.
- Resolve theme names through an allowlist and fall back to `bootstrap.min.css`.
- Do not construct a CSS path directly from an arbitrary session value.
- Add a defensive `.multi-collapse:not(.show) { display: none; }` rule to the unauthenticated screen.
- Display an explicit authentication failure message instead of rendering a visually broken page.
- Fix malformed heading closing tag and duplicate form field IDs.

## Scope

This is a regression hotfix for SB-00–SB-02. Authentication modernization remains SB-03/SB-04 work.
