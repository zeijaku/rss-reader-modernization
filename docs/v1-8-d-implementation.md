# V1.8-D Stock Pagination

## 目的

Stock件数が増えても全件を毎回SELECT・HTML生成せず、検索・並び替え条件を維持したまま20件単位で閲覧出来るようにする。

## DB Query

既存`content_stock`をそのまま利用し、DB変更は行わない。

最初に同じOwnership / active / Search条件で`COUNT(*)`を取得し、現在Pageを確定した後で表示対象だけを取得する。

基本条件:

```sql
WHERE stock_flag = 0
  AND stock_owner = :owner
```

検索時はV1.8-C / R2と同じTitle / URL条件と、重複しないPDO named placeholderを利用する。

表示QueryはValidation済みの整数から生成した`LIMIT 20 OFFSET n`を追加する。GETの`page`文字列をSQLへ直接連結しない。

## Page

GET Parameterは`page`。

- 未指定 / 不正値: 1
- 1ページ: 20件
- 検索・Sort Form送信時: `page`を送信しないため1ページへ戻る
- Total Pageを超えた値: 最終Pageへ補正
- 検索結果0件: Page 1扱い

## Pagination URL

ページLinkは`http_build_query()`で組み立てる。

保持する条件:

- `tab=stock`
- `q`（検索中のみ）
- `sort`（default以外）
- `page`（2ページ目以降）

Page 1では`page=1`を省略し、URLを不要に長くしない。

## UI

Bootstrap 4の通常Paginationを利用する。大量Pageで全ページ番号をHTML生成せず、現在Pageの前後2ページと最初・最後のPageを表示し、間は`…`で省略する。

ページLinkは44px以上のTouch targetを確保し、Smartphoneでも折り返せるようにする。

## Stock解除との整合

通常のStock解除はV1.8-Bと同様にAjaxで対象Cardだけを削除する。

Pagination中に現在画面の最後のCardを解除し、DBにはまだ表示可能なStockが残るケースでは、そのページを空のまま残さないよう、Serverが埋め込んだ安全なPagination URLへ移動する。通常の解除ではPage Reloadを追加しない。

## DB変更

なし。
