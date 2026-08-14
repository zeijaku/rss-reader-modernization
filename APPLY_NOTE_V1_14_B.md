# V1.14-B Apply Note

## Purpose

V1.14-B prepares the Bootstrap 5.3.8 / Bootswatch 5.3.8 frontend foundation without switching the active runtime yet.

V1.14-C will perform the Bootstrap 4 markup / utility / form / modal compatibility conversion and then switch runtime references to the staged Bootstrap 5 assets.

This separation keeps the V1.14-B checkpoint deployable on top of v1.13.0 without intentionally breaking existing Bootstrap 4 Data API markup such as `data-toggle`, `data-target`, or `data-dismiss`.

## Baseline

- Baseline: v1.13.0
- Database schema change: none
- Migration: none
- Required config change: none
- `APP_VERSION`: remains 1.13.0 for this development checkpoint

## Staged frontend assets

The V1.14-B focused workflow installs exact npm package versions and stages the following files:

- Bootstrap 5.3.8
  - `public/css/bootstrap-5.3.8.min.css`
  - `public/js/bootstrap.bundle-5.3.8.min.js`
- Bootswatch 5.3.8
  - `public/css/bootstrap-flatly-5.3.8.min.css`
  - `public/css/bootstrap-journal-5.3.8.min.css`
  - `public/css/bootstrap-minty-5.3.8.min.css`
  - `public/css/bootstrap-sketchy-5.3.8.min.css`
  - `public/css/bootstrap-slate-5.3.8.min.css`
  - `public/css/bootstrap-solar-5.3.8.min.css`
  - `public/css/bootstrap-yeti-5.3.8.min.css`

The workflow also creates `FRONTEND_ASSETS_V1_14_B.sha256` so the staged assets can be checked independently.

## Runtime behavior in V1.14-B

The existing active references remain unchanged in this phase:

- Bootstrap 4.1.3 CSS remains active.
- Bootswatch 4.1.3 theme CSS remains active.
- Bootstrap 4.1.3 JavaScript remains active.
- Standalone Popper remains active.
- jquery-drawer remains active.
- iScroll remains active.
- jQuery 3.7.1 remains active.

This is intentional. Runtime cutover is part of V1.14-C after the Bootstrap 4-specific markup has been converted.

## Focused validation

V1.14-B does not run the full regression suite.

The phase-specific workflow checks:

1. npm package versions are exactly Bootstrap 5.3.8 and Bootswatch 5.3.8.
2. All eight staged CSS files and the Bootstrap bundle are present.
3. The staged core files identify Bootstrap 5.3.8 / Bootswatch 5.3.8.
4. `bootstrap.bundle-5.3.8.min.js` contains Popper through the Bootstrap bundle package.
5. Current PHP pages still reference the existing unversioned Bootstrap 4 runtime files.
6. No V1.14-B staged asset has accidentally been enabled from production PHP markup.
7. PHP source syntax remains valid.
8. `git diff --check` passes.
9. A production verification ZIP containing `app/`, `public/`, this note, and the asset checksum manifest is generated as a workflow artifact.

## Deferred to V1.14-C

- `data-toggle` -> `data-bs-toggle`
- `data-target` -> `data-bs-target`
- `data-dismiss` -> `data-bs-dismiss`
- Bootstrap 4 form markup conversion
- Bootstrap 4 utility class conversion
- Modal markup compatibility fixes
- JavaScript-generated legacy Bootstrap attributes/classes
- Runtime CSS/JS switch to Bootstrap 5.3.8 / Bootswatch 5.3.8

## Deferred to later V1.14 phases

- jquery-drawer -> Bootstrap Offcanvas: V1.14-D
- iScroll / jquery-drawer / old standalone Popper removal: V1.14-E
- PC/SP and all-theme visual adjustment: V1.14-F
- Full regression and PHP 8.1 / PHP 8.4 release gate: V1.14-G

## Production confirmation for this checkpoint

Because V1.14-B does not activate the new assets, expected production behavior is intentionally identical to v1.13.0.

Confirm after extracting the package over a v1.13.0 test installation:

- Login and Dashboard display normally.
- Existing right-side Drawer opens/closes normally.
- At least one existing Modal opens/closes normally.
- Stock page and Settings page open normally.
- Current selected Bootswatch theme is unchanged.
- Browser console has no new JavaScript error.
- New `*-5.3.8.min.css` and `bootstrap.bundle-5.3.8.min.js` files exist, but Network/Elements confirm they are not loaded yet.

Do not delete the Bootstrap 4, Popper, Drawer, or iScroll assets in V1.14-B.
