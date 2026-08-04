# V1.2-A Apply Note

- Baseline: GitHub `main` commit `31e9d9f3fc594f8080d1962f10ac30985bd07881`
- Checkpoint: `RSS Reader Modernization 1.2.0-dev.1`
- DB Migration: なし
- SQL実行: 不要
- `config/local.php`追加: なし
- Feed Cache削除: 不要
- Browser Cache更新: 推奨
- `.htaccess`: Server設置Pathに応じてErrorDocumentの絶対Pathを手動確認
- 503 ErrorDocumentとMaintenance modeは別機能

`config/local.php`、実DB、Log、Session、Feed Cache、Login Throttle DataはZIPへ含めず、既存実環境Fileを上書きしない。

推奨Commit: `V1.2-A: modernize authentication and common errors`
推奨Branch: `feature/v1.2-a-auth-errors`
