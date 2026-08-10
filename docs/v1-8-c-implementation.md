# V1.8-C Stock検索・並び替え

## 目的

既存Stockデータを変更せず、保存後の記事をタイトル・URL・Domainから探し、新しい順 / 古い順 / タイトル順で表示出来るようにする。

## 実装方針

Browserへ全Stockを渡してJavaScript Filterする方式は採用せず、DB Queryで検索条件を適用する。V1.8-DでPaginationを追加する際に、同じQueryへ`COUNT(*)`と`LIMIT/OFFSET`を追加出来る構造を優先した。

## Search

GET Parameterは `q`。既存`app_validate_text()`でUTF-8、制御文字、最大128文字をValidationする。

検索対象:

- `stock_title`
- `stock_data`

URL全体を検索対象にするため、`qiita.com`等のDomain文字列でも検索可能。

LIKE Patternは`!`をEscape文字にして、`!` / `%` / `_`をLiteralとしてEscapeする。Query文字列はPDO ParameterとしてBindし、SQLへ直接連結しない。

## Sort

GET Parameterは `sort`。

許可値:

- `newest`: `stock_id DESC`
- `oldest`: `stock_id ASC`
- `title`: `stock_title ASC, stock_id DESC`

`app_validate_enum()`とDB側の`stock_search_order_by()`の二段階でwhitelist化し、未知の値は`stock_id DESC`へFallbackする。

## Ownership

検索条件を追加してもSQLの基本条件は維持する。

```sql
WHERE stock_flag = 0
  AND stock_owner = :owner
```

他ユーザーのStockや解除済みStockを検索結果へ混入させない。

## UI

Stock画面上部にBootstrap 4既存Classを使った簡易Filter Formを追加した。V1.8-EでStock一覧全体のCompact化を予定しているため、Cでは専用UI基盤や大規模CSS追加を行わない。

Query / SortはGET Formで保持する。検索0件時は通常の「Stockがまだない」状態と区別し、「条件に一致するStockはありません。」を表示する。

## DB変更

なし。
