# V1.13-B R2 Updated Files

Runtime:
- `.htaccess` — `/stock` を `public/stock.php` へ内部Rewrite。直接 `stock.php` を指定した場合は `/stock` へ302 Redirect。
- `public/.htaccess` — DocumentRootが `public/` の場合も同じExtensionless Routeを提供。
- `public/index.php` — 旧 `/?tab=stock` とDrawerの遷移先を `/stock` に変更。
- `public/stock.php` — 検索Form、Pagination、Clear、Drawer等のCanonical URLを `/stock` に変更。

Tests:
- `tests/test_v113b_stock_route.py` — Extensionless Route専用チェックを追加。
- `tests/run.sh` — 上記Focused Testを将来のFull Regressionへ追加。
- V1.13-B分離により `index.php` 固定だった一部の過去Static/Render Testを `stock.php` 分離後の構成へ追従。

DB / Migration / Config:
- DB変更なし
- Migration追加なし
- `config/local.php` 変更なし
