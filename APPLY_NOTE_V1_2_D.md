# V1.2-D / R1 適用メモ

- 基準: V1.2-C / R5
- Version: 1.2.0-dev.4
- Application変更:
  - `app/version.php`
  - `public/index.php`
  - `public/css/dashboard.css`
  - `public/js/dashboard.js`
- 内容:
  - 通常RSSとSearch Feedの記事左端をBookmarkから三点リーダーへ変更
  - 通常RSSとSearch Feedで1つの共通記事Actionsメニューを利用
  - 既存処理を再利用したStock保存
  - Clipboard APIとFallbackによる記事URLコピー
  - X Web Intentによる記事タイトル＋URLの投稿画面表示
  - 現在タブ内の先頭Task Widgetへ記事タイトルだけを追加
  - 外側Click、Esc、Scroll、Resize、記事更新時にメニューを閉じる
  - Keyboard操作、aria属性、44px操作領域、カード内位置調整へ対応
- Task追加先: 現在タブに表示される先頭のTask Widget
- Task登録内容: 記事タイトル、期限なし、通常優先度
- Taskへの記事URL保存: なし
- DB変更: なし
- SQL: 不要
- Migration: なし
- 外部API／外部Library追加: なし
- `.htaccess`: 変更なし
- `config/local.php`: 変更なし
- Feed Cache削除: 不要
- Browser Cache: 配置後のハードリロード推奨

## 配置

既存環境をBackupした後、上記Application変更4ファイルを配置してください。
`config/local.php`、実DB、`var/`配下のRuntime Data、サーバー用`.htaccess`は上書き不要です。
