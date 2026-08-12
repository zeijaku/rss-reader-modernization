# V1.13-C 更新ファイル

V1.13-B R2からの差分です。

## Production

- `.htaccess`
  - `/settings` Canonical URL / Rewrite追加
- `public/.htaccess`
  - DocumentRootがpublicの場合の `/settings` Rewrite追加
- `public/index.php`
  - Display / Tab / RSS Highlight Modalを分離
  - Drawerから `/settings#...` へLink
  - DashboardのRSS Highlight描画用Keyword JSONは維持
- `public/stock.php`
  - Display / Tab / RSS Highlight Modalを分離
  - Drawerから `/settings#...` へLink
- `public/settings.php`
  - 新規
  - Display Settings / Tab Settings / RSS Highlight管理
  - Account Settingsは独立Modalとして維持
  - private no-store / authenticated session / CSRF metaを維持

## Tests

- `tests/run.sh`
- `tests/test_v113c_settings_split.py` 新規
- `tests/test_v113c_settings_render.py` 新規
- `tests/test_v113c_settings_browser.py` 新規
- `tests/test_v113b_stock_split.py`
- `tests/test_sb11_12_static.py`
- `tests/test_m2d_responsive_ui.py`
- `tests/test_v11j_architecture.py`

既存Testの変更は、Settings UIの配置先が `index.php` / `stock.php` から `settings.php` へ変わったことへの追従のみです。

## Package documentation

- `APPLY_NOTE_V1_13_C.md`
- `CHECKLIST_FOR_USER_V1_13_C.md`
- `UPDATED_FILES_V1_13_C.md`
- `V1_13_C_BUILD.txt`
- `V1_13_C_TEST_REPORT.md`
- `V1_13_C_MANIFEST.sha256`
