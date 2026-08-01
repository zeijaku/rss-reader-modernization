# M2-D / R2 — Responsive・UI / UX

M2-C / R2をBaselineとし、画面構成を別物にせず、Feed / Stockの幅、長い文字列、操作Feedback、削除方法、空画面を整理しました。R2では実環境の目視確認で見つかったFeed列幅とDrawer密度の表示回帰を補正しました。

## R2表示補正

- fixed layout tableへ `colgroup` を追加し、Stock操作列を44px、記事列を残り幅へ固定。
- Drawerの通常項目を36px、paddingを5px 10pxへ戻した。
- Drawer section見出しの上下paddingを5pxへ縮小。
- `pointer: coarse` の端末では44pxと8px 12pxを維持し、Touch操作性を残した。
- DrawerのEscape、Tab循環、Focus復帰、ARIA処理は変更していない。

## Responsive

- Feed / Stockは `col-12 col-md-6 col-lg-3` を使用。
- Mobileは1列、Tabletは2列、Desktopは4列。
- PHPの4件単位row生成を外し、1つのBootstrap row内で折返す。
- Feed tableはfixed layoutとし、長い日本語、URL、英数字を折返す。
- Feed cardはLoading中も最低高さを持ち、初期表示の移動を抑える。
- Navbarの長いタブ名は省略表示し、Modal footerはMobileで縦並びにする。

## UI / UX

- RSS削除はURL空欄ではなく、明示Buttonと確認後の `content.delete` に変更。
- Feed errorへCard単位の「再読み込み」を追加。
- `alert()`を共通noticeへ置換し、Stock保存成功とMutation errorを表示。
- Stock保存ModalはAPI成功後に閉じる。
- RSS tabとStockで空画面を分ける。
- RSS追加Modalへ追加先タブ名を表示。Stock画面から追加する場合は従来どおりタブ1。
- RSS / Settings / Tab / Stock ModalとDrawerの主要文言を整理。

## 維持した範囲

- DB schema / migration
- API action / response contract
- Authentication / Authorization / Session / CSRF
- SSRF-safe Feed fetch
- RSS 2.0 / RSS 1.0 / Atom
- Item Identity / Cache / Lock / HTTP 304 / Retry / stale-if-error
- 4タブ、Feed CRUD、Stock、Settings
- Bootstrap / jQuery / Drawer / iScroll / Font AwesomeのVersion

Frontend Framework、npm、Build toolは追加していません。
