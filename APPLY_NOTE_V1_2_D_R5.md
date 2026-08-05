# V1.2-D / R5 適用メモ

## 変更内容

記事側の新着Bellが、2行目を含むTitle全体の横幅を常に消費していた表示を調整しました。

- BellをTitleの通常Flex幅から外して左上へ配置
- Bell分の余白をTitleの1行目だけに限定
- 2行目はTitle領域の左端から表示
- Bellの表示、解除操作、Keyboard操作を維持
- 通常RSSとSearch Feedの共通描画へ同じ調整を適用

三点リーダー、RSS概要「＋」、記事Actions、Stock、Taskには変更ありません。

## 配置

V1.2-D / R4を配置済みの場合、次の2ファイルを差し替えます。

- `public/css/dashboard.css`
- `public/js/dashboard.js`

配置後はブラウザーをハードリロードしてください。

`public/index.php`の変更はありません。

## DB・設定

- DB変更：なし
- SQL実行：不要
- Migration：なし
- `config/local.php`：変更なし、ZIP内なし
- `.htaccess`：変更なし
