# V1.2-A Changed Files

## Application

### Added

- `app/error_response.php`
- `public/error.php`
- `public/css/auth.css`
- `public/js/auth.js`

### Updated

- `.htaccess`
- `app/auth.php`
- `app/bootstrap.php`
- `app/common/common_login.php`
- `app/session.php`
- `app/version.php`
- `public/.htaccess`
- `public/api_v1.php`
- `public/index.php`
- `public/logout.php`

### Removed from attached deployment ZIP to match GitHub main inventory

- `public/js/jquery-3.3.1.min.js`
- `public/webfonts/fa-brands-400.eot`
- `public/webfonts/fa-brands-400.svg`
- `public/webfonts/fa-brands-400.woff`
- `public/webfonts/fa-regular-400.eot`
- `public/webfonts/fa-regular-400.svg`
- `public/webfonts/fa-regular-400.woff`
- `public/webfonts/fa-solid-900.eot`
- `public/webfonts/fa-solid-900.svg`
- `public/webfonts/fa-solid-900.woff`

上記はV1.1-KのFrontend Asset inventoryでは未使用・削除済みの配布物であり、GitHub `main`を基準に整理した。Font AwesomeのTTF／WOFF2は維持している。

## Tests

### Added

- `tests/error_http_router.php`
- `tests/test_v12a_architecture.py`
- `tests/test_v12a_auth_http.py`
- `tests/test_v12a_browser.py`
- `tests/test_v12a_error_http.py`

### Updated

- `tests/run.sh`
- `tests/session_http_router.php`
- `tests/static_checks.py`
- `tests/test_m2c_accessibility_structure.py`
- `tests/test_m2c_login_layout.py`
- `tests/test_m2e_asset_inventory.py`
- `tests/test_m2f_dependency_inventory.py`
- `tests/test_public_smoke.py`
- `tests/test_sb03_http.py`
- `tests/test_sb11_12_static.py`
- `tests/test_sb12_atom_link_static.py`
- `tests/test_sb13_sql.py`
- `tests/test_sb15_docs.py`
- `tests/test_version_marker.py`
- V1.1-B～JのVersion／Dashboard marker回帰Test

V1.1機能Testの本体条件は変更せず、後続の`1.2.0-dev.1`を「V1.1より後のCheckpoint」として許可した。

## Documentation

- `README.md`
- `CHANGELOG.md`
- `docs/v1-2-a-implementation.md`
- `docs/v1-2-a-files.md`
- `docs/v1-2-a-htaccess.diff`
- `docs/test-report-v1-2-a.md`
- `CHECKLIST_FOR_USER_V1_2_A.md`
- `APPLY_NOTE_V1_2_A.md`

## Database / Settings

- `database/`: 差分なし
- SQL: 追加なし
- `config/local.php`: 追加項目なし
