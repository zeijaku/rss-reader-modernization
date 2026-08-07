# V1.7-H Test report

## Scope

- Widget height Validation 1／2
- DB Schema／Migration 008／Preflight／Postflight
- 全Widget CRUDのAPI・Backend保存経路
- Dashboard CSS Grid 4列／2列／1列
- 高さ2のRow Span
- Add／Edit Modalの高さ設定
- DOM順と既存Drag／Keyboard順序
- Smartphone自動高
- Widget、Game、Timer、30日ログインの回帰

## Result

### V1.7-H focused tests

- Widget height／CRUD／render／architecture: PASS
- PHP syntax: 111 files PASS
- JavaScript syntax: 28 files PASS

### Segmented full regression

`tests/run.sh`は実行時間上限を超えるため、同じ順序を維持した重複しない区間へ分割して実行した。

- PASS: 6,199
- FAIL: 0
- SKIP: 12

SKIPは不足Extension、Headless Browser依存、過去正式版専用Release Gateによるもの。V1.7-H専用TestにはSKIPなし。

V1.2のBrowser模擬Testで一度だけTiming由来のFailureを検出したが、同じTestを単独再実行してPASS 18／FAIL 0を確認した。仕様や期待値は緩めていない。

### Environment limitation

この実行環境にはMySQL／MariaDB ServerとPDO MySQL Driverがないため、Migration 008の実Database適用は未実施。SQL構造、Prefix、Column定義、Application SQL、Test Fixtureで確認し、本番ではPreflight／Migration／Postflightを実行する。
