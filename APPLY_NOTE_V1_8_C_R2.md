# V1.8-C / R2 Apply Note

V1.8-C / R1のStock検索で、PDO MySQLのnative prepare時に同じ名前付きplaceholderを2回利用していたため、検索語を指定すると500になる不具合を修正します。

## 適用条件

- V1.8-C / R1適用済みSourceへ上書きしてください。
- DB Migration / SQL実行は不要です。
- `config/local.php` の変更も不要です。

## 修正内容

- `:stock_query` の再利用を廃止。
- `:stock_title_query` と `:stock_data_query` に分離。
- LIKE Escape、Ownership、`stock_flag = 0`、Sort whitelistは変更しません。
- Native PDO prepareで重複placeholderを検出するRegression Testを追加しました。

## Browser確認

1. Stock一覧で `AI` を検索し500にならないこと。
2. Title一致を確認。
3. `qiita.com` 等のURL/Domain一致を確認。
4. 新しい順 / 古い順 / タイトル順が引き続き動くこと。
5. Stock解除が引き続き動くこと。
