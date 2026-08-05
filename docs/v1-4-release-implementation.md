# Version 1.4.0正式化

## Baseline

- V1.4-D / R2
- Application Version: `1.4.0-dev.3`
- Baseline ZIP: `rss-reader-modernization-v1.4-d-r2.zip`

## 実施内容

- Application Versionを`1.4.0`へ確定。
- V1.4-B Game Widget基盤、V1.4-C Icon Quest、V1.4-D操作性・Storage Recovery、R2 Header余白修正をRelease範囲として整理。
- README、CHANGELOG、Release Notes、Update、Version、Package、GitHub Release手順を更新。
- Version 1.4専用Release GateとDocumentation Link Testを追加。
- Complete ZIPとRuntime ZIPのBuilder／VerifierをVersion 1.4.0へ更新。
- Full回帰を実施し、Packageを別Directoryへ再展開して構文とFocused回帰を確認。

## Application Runtime

V1.4-D / R2からのApplication Runtime変更は`app/version.php`だけです。Game Logic、CSS、JavaScript、API、DB、RSS Engineへの追加変更はありません。

## DB／設定

- Table／Column追加：なし
- Migration／SQL：なし
- 必須設定追加：なし
- `config/local.php`：変更・同梱なし
- Runtime Data：同梱なし

Game Widget登録は既存`dashboard_widget` Tableを使用し、Game状態はBrowser Storageへ保存します。

## Release Artifact

- `rss-reader-modernization-1.4.0-complete.zip`
- `rss-reader-modernization-1.4.0.zip`
- 各ZIPのSHA-256 Sidecar
- ZIP内部Manifest
