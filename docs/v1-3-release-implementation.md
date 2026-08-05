# Version 1.3.0正式化

## Baseline

- V1.3-D / R1
- Application Version: `1.3.0-dev.3`

## 実施内容

- Application Versionを`1.3.0`へ確定。
- V1.3-B Drawer、V1.3-C Header、V1.3-D共通余白をRelease範囲として整理。
- README、CHANGELOG、Release Notes、Update、Version、Package、GitHub Release手順を更新。
- Version 1.3専用Release GateとDocumentation Link Testを追加。
- Complete ZIPとRuntime ZIPのBuilder／VerifierをVersion 1.3.0へ更新。
- Full回帰を1回実施し、Packageを別Directoryへ再展開して構文とFocused回帰を確認。

## Application Runtime

V1.3-DからのApplication Runtime変更は`app/version.php`だけです。Header、Drawer、記事表示、JavaScript、API、DB処理への追加変更はありません。

## DB／設定

- Table／Column追加：なし
- Migration／SQL：なし
- API変更：なし
- 必須設定追加：なし
- `config/local.php`：変更・同梱なし
- Runtime Data：同梱なし

## Release Artifact

- `rss-reader-modernization-1.3.0-complete.zip`
- `rss-reader-modernization-1.3.0.zip`
- 各ZIPのSHA-256 Sidecar
- ZIP内部Manifest
