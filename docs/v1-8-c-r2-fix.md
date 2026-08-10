# V1.8-C / R2 Stock search 500 fix

## 原因

V1.8-C / R1では検索SQL内で同じ名前付きplaceholder `:stock_query` をTitle検索とURL検索の2箇所に再利用していた。
本ProjectはPDO接続時に `PDO::ATTR_EMULATE_PREPARES => false` を使用するため、MySQL native prepareではこのplaceholder再利用が不正となり、検索語が空でない場合にPDO例外から500へ到達する。

## 修正

Title検索とURL/Domain検索を以下の別placeholderへ分離した。

- `:stock_title_query`
- `:stock_data_query`

両方へ同じescaped LIKE patternをbindする。

DB schema、Index、Stock dataには変更を加えない。
