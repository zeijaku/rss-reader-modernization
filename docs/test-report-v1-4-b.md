# V1.4-B Test Report

## Test Level

Feature

Game Widget CRUD、Browser Storage、複数Widget、Dashboard描画、Responsive基盤を追加するため、QuickではなくFeature Testを選択した。

## V1.4-B Focused Test

- PHP Game Widget Domain／API: 28 PASS
- Architecture／Security／構造: 35 PASS
- JavaScript Storage Runtime: 26 PASS
- Dashboard Render: 20 PASS

合計: 109 PASS / 0 FAIL / 0 SKIP

## Full Regression

実行環境の1回あたりの時間上限を避けるため、`tests/run.sh`を同じ順序の3区間に分割して完走した。

- PASS: 4,987
- FAIL: 0
- SKIP: 12

重複実行したV1.1-Dの65 PASSは合計から除外している。

### SKIP内訳

- PDO SQLite Driverが実行環境にない: 1
- SimpleXML／mbstringが実行環境にない: 5
- Chromium Runtime依存不足: 1
- Version 1.0の旧正式版専用Gate: 2
- Version 1.1.0の旧正式版専用Gate: 1
- Version 1.2.0の旧正式版専用Gate: 1
- Version 1.3.0の旧正式版専用Gate: 1

V1.4-B専用TestにSKIPはない。

## 既存回帰で更新したTest

V1.4で追加したGame Type、新規Asset、開発Versionを正しく許容するため、過去Testの固定Allowlist／Version判定／Asset inventoryを更新した。既存機能の期待値は変更していない。

- `tests/test_m2e_asset_inventory.py`
- `tests/test_sb12_atom_link_static.py`
- `tests/test_sb13_sql.py`
- `tests/test_sb15_docs.py`
- `tests/test_version_marker.py`
- `tests/test_v11d_dashboard_widget.php`
- `tests/test_v11d_architecture.py`
- `tests/test_v12b_architecture.py`
- `tests/test_v12d_article_actions.py`
- `tests/test_v13b_drawer_structure.py`
- `tests/test_v13c_header_structure.py`
- `tests/test_v13d_spacing_structure.py`

## 確認対象

- Owner Scope
- Game Widget複数作成
- 別User更新／削除拒否
- `widget_reference_id=NULL`
- `widget_config`保存
- 5×5 Mock盤面
- Font Awesome Icon
- Grid Accessibility
- User／Widget別Storage Key
- `localStorage`／`sessionStorage`／Memory Fallback
- 壊れたJSON
- 未知Schema
- Reset
- Widget削除後Storage Cleanup
- Tab Swipe競合回避
- 44px Header／Cell
- `prefers-reduced-motion`
- 通常RSS／Search Feed／既存Widget／Header／Drawer／認証／Session／CSRF／SSRF／Cache

## 実行していないTest

Game本体が未実装のため、Player移動、Enemy AI、Treasure取得、Goal、勝敗、Score、途中盤面の本保存・復元はTestしていない。これらはV1.4-Cで追加する。
