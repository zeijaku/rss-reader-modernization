# Version 1.8.0正式化

## Baseline

- V1.8-E / R1
- Application Version: `1.8.0-dev.4`
- GitHub Source of Truth: Version 1.7.0の`main`を起点にV1.8-B～Eを適用

## 実施内容

- Application Versionを`1.8.0`へ確定。
- V1.8-BのStock解除、C/R2の検索／Sort、DのPagination、EのDomain／Actions／Compact ListをRelease範囲として整理。
- README、CHANGELOG、Release Notes、Update、Versioning、Release Package、PowerShell GitHub登録手順を更新。
- Complete／Runtime Package BuilderとVerifierをVersion 1.8.0へ更新。
- Version 1.8専用Release GateとDocumentation Link Testを追加。
- Full Regressionを区間分割で実行し、失敗区間だけ再確認する方針を採用。
- Complete ZIPとRuntime ZIPを生成し、再展開後のV1.8 focused regressionとSyntaxを実施。

## Application Runtime

V1.8-E/R1からのApplication Runtime変更は`app/version.php`だけです。正式化StageではStock SQL、API、JavaScript、CSS、Task連携へ新しい機能変更を追加しません。

## DB／Config

Version 1.8全体でTable／Column／Index／Migrationの変更はありません。既存`content_stock`をそのまま使用します。必須Config追加もありません。

## GitHub方針

GitHub `main`のVersion 1.7.0をBaselineとして扱い、Version 1.8.0 Complete Sourceを`feature/v1.8-stock`へRelease Commitとして反映します。`main`への統合はFast-forward限定、Tagは`v1.8.0`、Force push禁止です。GitHubへの書込みはこの成果物生成では行いません。
