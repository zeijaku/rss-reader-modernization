# V1.2-B / R2 Implementation

## Reason for correction

V1.2-B / R1では記事の概要Toggleと既存Stockを右側の同一Action領域へ配置していた。Smartphone幅およびThemeの組合せで、概要Chevronが薄く見え、Stock位置も従来の左側から変わっていた。

## Confirmed facts

- R1の記事行は`Title｜Summary・Stock`の2列構成だった。
- 右側Action列は90pxで、SummaryとStockの2Buttonを収容していた。
- 既存版ではStockは記事左端の44px列に配置されていた。
- API、DB、Feed Cache、Stock保存処理そのものには問題はなかった。

## Changes

- Feed tableを`Stock｜Title｜Summary`の3列へ変更。
- `.feed-stock-column`と`.feed-summary-column`を各44pxで固定。
- Stock Buttonを最初のCellへ復帰。
- Article Titleを中央Cellへ配置。
- Summary Toggleを最後のCellへ配置。
- Summary ToggleへUnicode `▽`を使用し、明示的なColorとSizeを設定。
- SummaryがないDisabled状態も、操作不可と分かる範囲でIconを視認可能にした。
- Loading、Empty、Error、Accordion detail rowの`colspan`を2から3へ変更。
- Pointer event抑制、Stock Modal、Summary Accordion、NEW状態、個別更新処理は維持。

## Non-changes

- DB／Migration／SQL: 変更なし
- `config/local.php`: 変更なし
- `.htaccess`: 変更なし
- API Action／payload: 変更なし
- Feed Cache／ETag／Last-Modified／Retry／Backoff: 変更なし
- Application Version: `1.2.0-dev.2`のまま

## Main files

- `public/index.php`
- `public/js/dashboard.js`
- `public/css/dashboard.css`
- `tests/test_m2b_feed_runtime.js`
- `tests/test_m2c_dashboard_render.py`
- `tests/test_m2d_dashboard_render.py`
- `tests/test_m2d_r2_layout_regression.py`
- `tests/test_v12b_architecture.py`
- `tests/test_v12b_browser.py`
