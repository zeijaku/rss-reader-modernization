# APPLY NOTE — V1.19-E / 1.19.0-RC1

本番互換確認用Release Candidateです。正式Releaseではありません。

- Base: V1.18.0 + V1.19-B/C/D/E
- APP_VERSION: `1.19.0-rc1`
- APP_VERSION_LABEL: `RSS Reader Modernization 1.19.0-RC1`
- APP_ASSET_REVISION: `1.19.0-rc1`
- DB Migration / SQL: なし
- 新規必須Config / Secret: なし
- GitHub write: なし
- Automated Release Gate: PASS

Runtime RC ZIPをApplication Rootへ展開し、既存の`config/local.php`、実DB、生成済み`var/`Dataは維持してください。

本番確認後に問題がなければ、V1.19-FでVersionを正式`1.19.0`へ確定し、最終Release packageを作成します。
