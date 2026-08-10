# V1.3-C / R1 Apply Note

- Direct checkpoint baseline: `rss-reader-modernization-v1.3-b-r1.zip`
- Checkpoint: `RSS Reader Modernization 1.3.0-dev.2`
- DB Migration: なし
- SQL実行: 不要
- `config/local.php`追加: なし
- Root / Public `.htaccess`変更: なし
- API / Session / RSS Parser / JavaScript変更: なし
- Browser Cache更新: CSSとPHP Template差し替え確認のためHard Reload推奨
- 外部依存 / Build環境追加: なし

`config/local.php`、実DB、Log、Session、Feed Cache、Login Throttle DataなどのRuntime DataはZIPへ含めない。既存実環境Fileを上書きしない。

推奨Branch: `feature/v1.3-c-header`

推奨Commit: `V1.3-C: organize application header`
