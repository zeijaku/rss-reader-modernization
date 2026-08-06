# Version 1.5.0正式化

## Baseline

- V1.5-C / R5
- Application Version: `1.5.0-dev.2`
- Baseline ZIP: `rss-reader-modernization-v1.5-c-r5.zip`
- Baseline SHA-256: `68c4d20009b9873a2cb688571178ad17cc1ffbf75d41d291aac7dab642f5e84d`

## 実施内容

- Application Versionを`1.5.0`へ確定。
- V1.5-B Clock Timer、V1.5-C Recovery／複数Tab同期、R2～R5のSmartphone／終了表示調整をRelease範囲として整理。
- README、CHANGELOG、Release Notes、Update、Version、Package、GitHub Release手順を更新。
- Version 1.5専用Release GateとDocumentation Link Testを追加。
- Complete ZIPとRuntime ZIPのBuilder／VerifierをVersion 1.5.0へ更新。
- Full回帰を実施し、Packageを別Directoryへ再展開して構文とV1.5 Focused回帰を確認。

## Application Runtime

V1.5-C / R5からのApplication Runtime変更は`app/version.php`だけです。Timer Logic、CSS、JavaScript、Feed、Game、API、DB、RSS Engineへの追加変更はありません。

## DB／設定

- Table／Column追加：なし
- Migration／SQL：なし
- 必須設定追加：なし
- `config/local.php`：変更・同梱なし
- Runtime Data：同梱なし

Timer状態はBrowser Storageへ保存し、Clock Widget登録は既存`dashboard_widget` Tableを利用します。

## Release Artifact

- `rss-reader-modernization-1.5.0-complete.zip`
- `rss-reader-modernization-1.5.0.zip`
- 各ZIPのSHA-256 Sidecar
- ZIP内部Manifest
