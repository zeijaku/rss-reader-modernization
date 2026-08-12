# V1.13-C Test Report

## Scope

V1.13-C separates Display Settings, Tab names, and RSS Highlight management from Dashboard/Stock into `public/settings.php`, exposed through the canonical extensionless URL `/settings`.

The release candidate is based on the user-verified V1.13-B R2 package. No DB schema, migration, or private configuration change is included.

## V1.13-C dedicated tests

- `tests/test_v113c_settings_split.py`: **87 PASS / 0 FAIL**
  - authenticated/private page boundary
  - `noindex,nofollow`
  - three Settings sections moved from Dashboard/Stock
  - Account Settings remains separate
  - Drawer links use `/settings#display`, `/settings#tabs`, `/settings#highlight`
  - Dashboard keeps RSS Highlight keyword JSON required by feed rendering
  - Settings mutations remain on central `api_v1.php` + CSRF
  - extensionless rewrite rules
  - output escaping / asset scope / no direct SQL / no migration
  - encoding, BOM, EOL, trailing whitespace checks
- `tests/test_v113c_settings_render.py`: **28 PASS / 0 FAIL**
  - authenticated Settings render with mocked PDO
  - current theme/navbar/tab values
  - XSS escaping for tab name, URL, keyword, JSON data
  - CSRF meta and logout form
  - no duplicate IDs
- `tests/test_v113c_settings_browser.py`: **19 PASS / 0 FAIL**
  - Playwright/Chromium interaction
  - `settings.update`, `tabs.update`, `feed.keyword.create`, `feed.keyword.delete`
  - CSRF transmission
  - button/pending state recovery
  - keyword list/count DOM updates without page reload
  - client does not send `user_id`

Dedicated V1.13-C total: **134 PASS / 0 FAIL**.

## Related focused regression

### V1.13-B / Stock split

- `tests/test_v113b_stock_split.py`: **84 PASS / 0 FAIL**
- `tests/test_v113b_stock_route.py`: **15 PASS / 0 FAIL**
- V1.8 Stock search/render/pagination/tag/task/Ajax removal focused tests: PASS
  - `test_v18c_stock_search_static.py`
  - `test_v18c_stock_helpers.php` — 6 PASS
  - `test_v18c_stock_render.php` — 11 PASS
  - `test_v18d_stock_pagination_static.py` — 22 PASS
  - `test_v18d_stock_pagination.php` — 17 PASS
  - `test_v18d_stock_page_clamp.php` — 6 PASS
  - `test_v18e_stock_ui_static.py` — 22 PASS
  - `test_v18e_stock_task_targets.php` — 9 PASS
  - `test_v18e_stock_render.php` — 13 PASS
- `test_v18b_stock_static.py`: PASS
- `test_v18b_stock_db.php`: **SKIP in this local environment only** because PDO SQLite is not installed. Other PDO-mock based Stock runtime tests passed.

### Account Settings

- `tests/test_v11j_architecture.py`: **86 PASS / 0 FAIL**
- `tests/test_v11j_dashboard_render.py`: PASS
- `tests/test_v11j_frontend_runtime.js`: **45 PASS / 0 FAIL**
- `tests/test_v11j_browser.py`: **34 PASS / 0 FAIL**

### Settings/API/Security

- `tests/test_sb11_12_static.py`: **47 PASS / 0 FAIL**
- `tests/test_m2d_responsive_ui.py`: **52 PASS / 0 FAIL**
- `tests/test_sb05_07_api.php`: **44 PASS / 0 FAIL**
- `tests/test_sb10_output_static.py`: **35 PASS / 0 FAIL**
- `tests/test_sb14_surface_static.py`: PASS
- `tests/test_v12d_article_actions.py`: **25 PASS / 0 FAIL**

### Header / responsive browser regression

- `tests/test_v13c_header_structure.py`: **69 PASS / 0 FAIL**
- `tests/test_v13c_header_browser.py`: **672 PASS / 0 FAIL / 0 SKIP**
  - themes
  - Navbar styles
  - viewport widths

## Syntax / rewrite / packaging checks

- `php -l public/index.php`: PASS
- `php -l public/stock.php`: PASS
- `php -l public/settings.php`: PASS
- `bash -n tests/run.sh`: PASS
- `git diff --check` equivalent against V1.13-B R2: PASS
- Apache 2.4 root deployment rewrite:
  - `/settings` -> internal `public/settings.php`: PASS
  - `/settings?x=1`: PASS
  - `/settings.php?x=1` -> 302 `/settings?x=1`: PASS
  - `/public/settings.php?q=AI` -> 302 `/settings?q=AI`: PASS
  - `/stock` still works: PASS
- Apache 2.4 subdirectory deployment:
  - `/rss/settings`: PASS
  - `/rss/settings.php?x=1` -> `/rss/settings?x=1`: PASS
  - `/rss/public/settings.php?x=1` -> `/rss/settings?x=1`: PASS
- unauthenticated direct `settings.php`: 302 to `./`, private/no-store headers, empty body: PASS

## Full regression policy

`tests/run.sh` was **not** run in full for this phase. This is intentional: V1.13 uses focused tests for structural sub-phases and reserves the complete regression suite for the V1.13-G checkpoint/release, avoiding repeated long full-suite runs after each small structural step.

No focused test failure remains in this V1.13-C candidate.
