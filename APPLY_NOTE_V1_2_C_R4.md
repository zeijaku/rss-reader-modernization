# V1.2-C / R4 適用メモ

- 基準: V1.2-C / R3
- Version: 1.2.0-dev.3（変更なし）
- Application変更: `public/js/dashboard.js`
- 内容:
  - 「Stockへ保存しました」を2.5秒後に自動消去
  - Search Feedの記事概要ButtonからSearch Feedカードを正しく参照
  - 「RSS概要を確認出来ませんでした」を4秒後に自動消去
- 空概要の`＋`: 従来どおり表示したままdisabled
- Memoの`sessionStorage`対応: 今回は未実施
- DB変更: なし
- SQL: 不要
- `.htaccess`: 変更なし
- `config/local.php`: 変更なし
- Feed Cache削除: 不要
- Browser Cache: ハードリロード推奨
