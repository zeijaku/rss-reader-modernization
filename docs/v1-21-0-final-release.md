# RSS Reader Modernization Version 1.21.0 Final Release

Version 1.21.0 is finalized from the formal Version 1.20.1 baseline and the reviewed V1.21-A, V1.21-B, and V1.21-C changes.

## Final scope

- Drawer category / information architecture cleanup
- Restrained visual hierarchy and Current / Danger states
- Smartphone / Touch fit, scroll, safe-area, and accordion-chevron refinement
- Documentation, deterministic Runtime / Complete Source packaging, regression, compatibility, and secret checks

## Explicitly unchanged

- Bootstrap 5 Offcanvas remains the Drawer implementation.
- Existing jQuery-assisted behavior remains where it already works.
- Database schema and migrations are unchanged by Version 1.21.
- Production `config/local.php` contract is unchanged.

## Deferred

- File Upload / File Library / Image Viewer
- Imgur Random / Gallery Widget
- Whole-dashboard Grid alignment for Height 2 Widgets

## Release identity

The immutable Git tag `v1.21.0` is the authoritative source identity for the release. The GitHub Release attaches the Runtime ZIP, Complete Source ZIP, and their SHA-256 sidecars produced by the release gate from that exact commit.

The existing `v1.20.1` tag must never be moved or overwritten.
