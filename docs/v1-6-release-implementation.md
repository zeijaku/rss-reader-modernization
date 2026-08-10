# Version 1.6.0正式化

## Baseline

- V1.6-D / R1
- Application Version: `1.6.0-dev.3`
- Baseline ZIP: `rss-reader-modernization-v1-6-d-r1.zip`
- Baseline SHA-256: `a02fbc451f1e64984d55d7ff89b7d5d6142a0e3c257e63805db889b392f91de5`

## 実施内容

- Application Versionを`1.6.0`へ確定。
- V1.6-B Swipe Indicator、V1.6-C Lights Out基本機能、V1.6-D状態保持／品質調整をRelease範囲として整理。
- README、CHANGELOG、Release Notes、Update、Version、Package、GitHub Release手順を更新。
- Version 1.6専用Release GateとDocumentation Link Testを追加。
- Complete ZIPとRuntime ZIPのBuilder／VerifierをVersion 1.6.0へ更新。
- Full Regressionを実施し、Packageを別Directoryへ再展開してRelease GateとV1.6 Focused回帰を確認。

## Application Runtime

V1.6-D / R1からのApplication Runtime変更は`app/version.php`だけです。Swipe、Lights Out、Timer、Icon Quest、Feed、API、DB、RSS Engineへの追加変更はありません。

## DB／設定

- Table／Column追加：なし
- Migration／SQL：なし
- API Route変更：なし
- 必須設定追加：なし
- `config/local.php`：変更・同梱なし
- Runtime Data：同梱なし

Lights Out Widget登録は既存`dashboard_widget` Tableを利用し、進行状態はBrowser Storageへ保存します。

## Release Artifact

- `rss-reader-modernization-1.6.0-complete.zip`
- `rss-reader-modernization-1.6.0.zip`
- 各SHA-256 Sidecar
- ZIP内部Manifest

Git Tagは作成していません。本番確認後に`v1.6.0`をmainのRelease Commitへ作成します。
