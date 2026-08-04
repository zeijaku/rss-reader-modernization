# V1.2-B Apply Note

- Upstream baseline: GitHub `main` commit `31e9d9f3fc594f8080d1962f10ac30985bd07881`
- Direct baseline: `rss-reader-modernization-v1.2-a-r1.zip`
- Checkpoint: `RSS Reader Modernization 1.2.0-dev.2`
- DB Migration: なし
- SQL実行: 不要
- `config/local.php`追加: なし
- Root／Public `.htaccess`変更: なし
- Feed Cache削除: 不要
- Browser Cache更新: 推奨
- 新API Action: なし
- 外部依存／Build環境追加: なし

`config/local.php`、実DB、Log、Session、Feed Cache、Login Throttle DataはZIPへ含めず、既存実環境Fileを上書きしない。

推奨Commit: `V1.2-B: improve feed articles and individual refresh`
推奨Branch: `feature/v1.2-b-feed-articles`
