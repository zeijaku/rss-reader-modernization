# V1.3-B / R1 Apply Note

- Direct baseline: `rss-reader-modernization-1.2.0-complete.zip`
- Checkpoint: `RSS Reader Modernization 1.3.0-dev.1`
- DB Migration: なし
- SQL実行: 不要
- `config/local.php`追加: なし
- Root / Public `.htaccess`変更: なし
- API / Session / RSS Parser変更: なし
- Browser Cache更新: CSSとPHP Template差し替え確認のためHard Reload推奨
- 外部依存 / Build環境追加: なし

`config/local.php`、実DB、Log、Session、Feed Cache、Login Throttle DataなどのRuntime DataはZIPへ含めていない。既存実環境Fileを上書きしない。

推奨Branch: `feature/v1.3-b-drawer-menu`

推奨Commit: `V1.3-B: organize drawer navigation`
