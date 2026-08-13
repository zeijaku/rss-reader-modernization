# V1.13-D Test Report

## Scope

V1.13-D is a structure-only refactor of the Dashboard entry point. It moves the existing Widget render block and Dashboard Modal block from `public/index.php` into two coarse internal View files without per-Widget fragmentation.

The candidate is based on the user-verified V1.13-C package / GitHub `feature/v1.13-structure` C commit. No DB schema, migration, runtime configuration, API action, JavaScript, or CSS change is included.

## Source relocation equivalence

V1.13-C `public/index.php` SHA-256:

`bb9f03e66cc263260d14b210f0720ce019ec0c071bbf9acbac7c09fbb32e19d4`

The V1.13-D test helper expands `dashboard_widgets.php` and `dashboard_modals.php` back into their include positions. The reconstructed source is byte-for-byte identical to the V1.13-C index source and has the same SHA-256.

This is a one-time refactor verification result; the hash is intentionally not hard-coded into future regression tests so later legitimate Dashboard changes are not blocked.

## V1.13-D dedicated structure test

- `tests/test_v113d_index_views.py`: **58 PASS / 0 FAIL**
  - exactly two coarse Dashboard internal Views
  - no per-Widget file fragmentation
  - Widget types remain in one View
  - Modal groups remain in one View
  - index retains Session/auth/legacy Stock redirect/Navbar/Drawer/assets
  - Views do not become standalone Entry Points
  - Views add no request parsing or direct PDO path
  - UTF-8 BOM absent / LF maintained / trailing whitespace absent

## Legacy static source regression

Legacy tests that intentionally inspect Dashboard source now use `tests/dashboard_source_utils.py`, which expands the internal Views in place before applying the existing assertions.

- Dashboard source-aware focused scripts: **35 PASS / 0 FAIL / 0 TIMEOUT**
- Existing assertions were not relaxed to accommodate the split.
- Two old M2-C Settings assertions that still looked for V1.13-C Settings fieldsets in `index.php` were corrected to inspect `public/settings.php`.

## PHP Dashboard render regression

Representative server-rendered Dashboard coverage:

- `test_v11d_dashboard_render.py`
- `test_v11f_dashboard_render.py`
- `test_v11g_dashboard_render.py`
- `test_v11h_dashboard_render.py`
- `test_v11i_dashboard_render.py`
- `test_v11j_dashboard_render.py`
- `test_v14b_dashboard_render.py`
- `test_v14c_dashboard_render.py`
- `test_v14d_dashboard_render.py`
- `test_v15c_dashboard_render.py`
- `test_v16c_dashboard_render.py`
- `test_v17h_dashboard_render.py`

Result: **12 scripts PASS / 0 FAIL**.

A V1.7-H mixed Feed + Clock PDO-mock fixture was also rendered against both V1.13-C and V1.13-D:

- HTML response size: **99,155 bytes** on both
- randomized CSRF token values were normalized for comparison
- normalized HTML: **exactly identical**

## Real browser focused regression

- `tests/test_v13c_header_browser.py`: **672 PASS / 0 FAIL / 0 SKIP**
  - themes / navbar schemes / responsive widths / Drawer keyboard focus
- `tests/test_v11j_browser.py`: **34 PASS / 0 FAIL**
  - Account Settings Modal behavior
- `tests/test_v12d_article_actions_browser.py`: **34 PASS / 0 FAIL**
  - article Actions / feed refresh behavior
- `tests/test_v16c_browser.py`: **11 PASS / 0 FAIL / 0 SKIP**
  - Game interaction / touch size / dark Theme

Browser focused total: **751 PASS / 0 FAIL / 0 SKIP**.

## V1.13-B / V1.13-C continuity

- `tests/test_v113b_stock_split.py`: **84 PASS / 0 FAIL**
- `tests/test_v113b_stock_route.py`: **15 PASS / 0 FAIL**
- `tests/test_v113c_settings_split.py`: **87 PASS / 0 FAIL**
- `tests/test_v113c_settings_render.py`: PASS
- `tests/test_v113c_settings_browser.py`: **19 PASS / 0 FAIL**

Stock and Settings production files are SHA-256-identical to the V1.13-C baseline.

## Security / API / output focused regression

- `tests/test_sb05_07_api.php`: **44 PASS / 0 FAIL**
- `tests/test_sb10_output_static.py`: **35 PASS / 0 FAIL**
- `tests/test_sb14_surface_static.py`: PASS
- `tests/test_v12d_article_actions.py`: **25 PASS / 0 FAIL**

No direct SQL, new mutation path, Session boundary, or CSRF path was added by the internal Views.

## Syntax / diff / scope checks

- PHP syntax: **136 files checked / 0 FAIL**
- `bash -n tests/run.sh`: PASS
- `git diff --check` against V1.13-C baseline: PASS
- UTF-8 BOM: none in changed Production files
- EOL: LF maintained
- trailing whitespace: none
- DB migration diff: none
- Config diff: none
- `public/stock.php`: unchanged
- `public/settings.php`: unchanged
- `public/js/dashboard.js`: unchanged
- `public/css/dashboard.css`: unchanged
- `app/api.php`: unchanged
- `app/common/common_db.php`: unchanged

## Source size comparison

| File | V1.13-C | V1.13-D |
|---|---:|---:|
| `public/index.php` | 147,418 bytes | 26,443 bytes |
| `app/view/dashboard_widgets.php` | - | 49,860 bytes |
| `app/view/dashboard_modals.php` | - | 71,256 bytes |
| total of the three files | 147,418 bytes | 147,559 bytes |

The `index.php` reduction is approximately 82%, but total source size is effectively unchanged. This is a readability/maintenance improvement, not a Performance claim.

## Full regression policy

`tests/run.sh` was **not** run in full for V1.13-D. This follows the V1.13 plan: structural sub-phases use broad Focused Tests and the complete regression + PHP 8.1 / 8.4 CI is reserved for V1.13-G.

No GitHub Actions workflow was started during this candidate build.
