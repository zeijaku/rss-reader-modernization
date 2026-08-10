# Version 1.6.0 Full Regression Test Report

## Result

```text
PASS 6,200
FAIL 0
SKIP 14
```

PHP構文確認は102ファイルすべて正常でした。Full Runnerは実行時間上限を避けるため、`tests/run.sh`と同じ順序・条件を維持した区間分割で完走しています。

## Scope

- Secure Baseline SB-00～15
- M1-A～G RSS Engine
- M2-A～F Frontend
- V1.1 Dashboard、Widget、Account
- V1.2 Authentication、Feed UX、Search、Actions
- V1.3 Drawer、Header、Spacing
- V1.4 Icon Quest／Game Widget
- V1.5 Clock Timer
- V1.6 Swipe Indicator／Lights Out／Storage／Accessibility
- Version 1.6.0 Release Gate
- Documentation Link
- Repository／Secret／Runtime Data Scan

## SKIP

| 項目 | 理由 |
|---|---|
| PDO SQLite integration | 実行環境にPDO SQLite Driverがない |
| live SimpleXML fixture parsing | 実行環境にSimpleXML／mbstringがない |
| SB-14 live parser matrix | SimpleXML／mbstringが必要 |
| M1-A live normalized parser | SimpleXML／mbstringが必要 |
| M1-C live adapter matrix | SimpleXML／mbstringが必要 |
| M1-D live identity adapter matrix | SimpleXML／mbstringが必要 |
| M2-F Chromium smoke | 当該Smoke Testが要求するRuntime dependencyが不足 |
| M2-G Version 1.0 gate | Version 1.0専用の履歴Gate |
| M4-A～G Version 1.0 gate | Version 1.0専用の履歴Gate |
| V1.1-K release gate | APP_VERSION 1.1.0専用 |
| Version 1.2 release gate | APP_VERSION 1.2.0専用 |
| Version 1.3 release gate | APP_VERSION 1.3.0専用 |
| Version 1.4 release gate | APP_VERSION 1.4.0専用 |
| Version 1.5 release gate | APP_VERSION 1.5.0専用 |

## Release Gateで発見したTest調整

V1.4～V1.6の一部Architecture Testが、V1.6開発Checkpoint Labelだけを許可し、正式版`RSS Reader Modernization 1.6.0`を到達点として許可していませんでした。機能・仕様の期待値は変更せず、正式版Version／Labelだけを後方互換な到達点として追加しました。

## Package／再展開

Complete ZIPとRuntime ZIPについて次を確認します。

- SHA-256 Sidecar
- CRC
- 重複Entry
- Absolute Path／Parent Traversal／Backslash Path
- 内部Manifest
- Version Marker
- Private設定、実DB、Runtime Data除外
- 別Directoryへの再展開
- 再展開後のV1.6 Release GateとFocused回帰

## 実機・本番で必要な確認

- iPhone Safariの画面端戻るGesture
- Android ChromeのGesture Navigation
- 本番PHP／MySQL／Web Server
- 本番Database Update／Rollback
- GitHub main、Release Commit、Tag、GitHub Actions
