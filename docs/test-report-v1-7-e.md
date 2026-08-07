# V1.7-E Test Report

## Scope

V1.7-D / R1をBaselineとして、Remember TokenのDB定義とBackend処理を確認しました。Login画面、Cookie発行、自動Login、Session再生成、Logout／Password変更への接続はV1.7-Fの範囲であり、V1.7-Eでは未導入です。

## Dedicated tests

### Remember Token runtime

```text
PASS 37
FAIL 0
SKIP 0
```

確認内容：

- Selector 24文字、Validator 64文字
- Strict Parse
- Raw Validator非保存
- SHA-256 Hash保存
- 固定30日期限
- Active Userだけへの発行
- Validator Rotation
- 旧Validator Replay拒否
- Expired Token削除
- Inactive User Token削除
- Current Token失効
- User全Token失効
- Expired Cleanup
- DB失敗時のTransaction Rollback

### Architecture / migration boundary

```text
PASS 44
FAIL 0
SKIP 0
```

確認内容：

- Migration 007とSchemaの一致
- Selector Unique Index
- User／Expiry Index
- Expiry Cleanup Index
- Foreign Keyを追加していないこと
- Table Prefix対応
- `config/local.php`追加なし
- Login UI／Cookie／自動Login統合が未導入であること
- V1.7-F向け関数境界

## Full regression

単一Runnerは実行時間上限へ達したため、`tests/run.sh`と同じ順序・条件を維持して非重複区間へ分割し、V1.4-D / R2の独立Testも追加実行しました。

```text
PASS 6,092
FAIL 0
SKIP 14
```

SKIPは、実行環境に不足するPDO SQLite、SimpleXML、mbstring、Chromium関連Runtime、および旧正式Version専用Release Gateです。V1.7-E専用TestにはSKIPはありません。

## Syntax

```text
PHP        107 files PASS
JavaScript 26 files PASS
```

## Environment limitation

実行環境にはMySQL／MariaDB ServerとPDO MySQL Driverがないため、Migration 007を実Databaseへ適用するTestは実施していません。SQL構造、Prefix展開、Index、Backend SQL、Transaction、Rotation、失効処理はStatic TestとTest Doubleで確認しています。本番適用前にPreflight、Migration、Postflightを順番に実行してください。
