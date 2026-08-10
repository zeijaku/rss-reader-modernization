# Version 1.7.0正式化

## Baseline

- V1.7-H / R4
- Application Version: `1.7.0-dev.10`
- Baseline ZIP: `rss-reader-modernization-v1-7-h-r4.zip`
- Baseline SHA-256: `c6bf8c6f8d2d3e3ea87bc5c55a2018bbe345f73179cb7fd7fe1befe6833f9d51`

## 実施内容

- Application Versionを`1.7.0`へ確定。
- V1.7-C～DのCache／Header、V1.7-E～FのRemember Login、V1.7-G～HのWidget Grid、R2～R4の実機調整と祝日対応をRelease範囲として整理。
- README、CHANGELOG、Release Notes、Update、Versioning、Release Package、PowerShell GitHub登録手順を更新。
- `.gitignore`へV1.7 Migration／Audit SQLのAllow ruleを追加し、GitHub同期時のIgnore漏れを防止。
- Release Builder／VerifierをVersion 1.7.0へ更新し、Holiday Runtime Cacheを除外対象へ拡張。
- Version 1.7専用Release GateとDocumentation Link Testを追加。
- Complete ZIPとRuntime ZIPを生成し、再展開後のFocused Regressionを実施。

## Application Runtime

V1.7-H/R4からのApplication Runtime変更は`app/version.php`だけです。Calendar、RSS、Grid、Remember Login、API、DB処理へ正式化段階の機能変更は追加しません。

## DB／Config

Version 1.7全体ではMigration 007／008があります。V1.7-H/R4適用済み環境では再実行しません。

Holiday取得設定は`config/local.php.example`／`.env.example`に含まれますが、Default値があるため新しい秘密情報はありません。実`config/local.php`は配布物へ含めません。

## GitHub方針

Version 1.5／1.6の欠けた履歴を再構築せず、Version 1.7.0 Complete Sourceを既存`feature/v1.7-modernization`へRelease Commitとして反映します。`main`への統合はFast-forward限定、Tagは`v1.7.0`、Force push禁止です。
