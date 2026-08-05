# V1.2-D / R3 適用メモ

## 変更内容

記事右端にあるRSS概要の「＋」周辺だけを微調整しました。

- RSS概要列を44pxから36pxへ縮小
- RSS概要ボタンの横幅を44pxから36pxへ縮小
- ボタンの高さ44pxは維持
- 記事タイトル領域を8px拡張

通常RSSとSearch Feedの共通表示に同じ調整が適用されます。
概要の開閉処理、disabled表示、三点リーダー、記事Actionsには変更ありません。

## 配置

V1.2-D / R2を配置済みの場合、次のファイルだけを差し替えます。

- `public/css/dashboard.css`

配置後はブラウザーをハードリロードしてください。

## DB・設定

- DB変更：なし
- SQL実行：不要
- Migration：なし
- `config/local.php`：変更なし、ZIP内なし
- `.htaccess`：変更なし
