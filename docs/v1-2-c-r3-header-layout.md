# V1.2-C / R3 Search Feed Header Layout

## 対応範囲

Search Feed見出しの表示だけを修正した。

- 通常RSSで使用している`feed-card-header`と`feed-card-header-inner`をSearch Feedにも適用
- 見出し内を`Drag Handle / Title / Edit / Refresh`の1段Flex Layoutへ統一
- Edit／Refreshは44pxの操作領域を維持
- 長い検索語句は折り返さずEllipsis表示

検索API、Search条件、Cache、記事Renderer、Stock、DBには変更していない。

## 変更ファイル

- `public/index.php`
- `public/css/dashboard.css`
- `tests/test_v12c_r3_header_layout.py`
- `tests/run.sh`
- Documentation／Manifest
