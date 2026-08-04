# V1.2-B / R3 Apply Note

- Upstream baseline: GitHub `main` commit `31e9d9f3fc594f8080d1962f10ac30985bd07881`
- Direct baseline: `rss-reader-modernization-v1.2-b-r2.zip`
- Checkpoint: `RSS Reader Modernization 1.2.0-dev.2`（V1.2-B / R3）
- DB Migration: なし
- SQL実行: 不要
- `config/local.php`追加: なし
- Root／Public `.htaccess`変更: なし
- Feed Cache削除: 不要
- Browser Cache更新: **必要（CSS／JavaScript差し替え確認のためHard Reload推奨）**
- 新API Action: なし
- 外部依存／Build環境追加: なし

`config/local.php`、実DB、Log、Session、Feed Cache、Login Throttle DataはZIPへ含めず、既存実環境Fileを上書きしない。

推奨Commit: `V1.2-B: improve feed articles and individual refresh`
推奨Branch: `feature/v1.2-b-feed-articles`

## R2 correction

- Stock Iconを記事行の左側へ戻した。
- 概要`▽`を右側の独立列へ移し、44pxの操作領域と明示的な表示色を設定した。
- SQL、DB Migration、Feed Cache削除は不要。

## R3 correction

- 記事Titleを自然な1行～最大2行表示へ変更。
- 1行Title時の余分な高さをなくし、Stock／NEW／概要Buttonを縦中央へ揃えた。
- 概要操作をFont AwesomeのPlus／Minus Squareへ変更。
- SQL、DB Migration、Feed Cache削除は不要。
