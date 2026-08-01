# M2-A / R1 — Frontend基盤整理

## 目的

見た目や既存機能を大きく変えず、`public/index.php` に集中していたDashboard固有JavaScript / CSSを外部Assetへ分離します。M1-G / R1をBaselineとし、Legacy版へ戻していません。

## 変更内容

- `public/js/dashboard.js` を追加。
- `public/css/dashboard.css` を追加。
- PHP生成の `fetch_content(<id>)` を廃止し、`data-feed-content-id` を走査してFeedを取得。
- Feed取得時はURLをAPI Requestへ直接送らず、従来どおり `content_id` だけを `feed.fetch` へ送信。編集モーダル用の既存hidden valueは維持。
- API Request helper、error message、Event登録を外部JSへ移動。
- Event namespaceと初期化済み判定で二重登録を防止。
- state-changing requestへpending guardを追加し、通信中の連続送信を防止。
- CSSは既存のPage Topとtable hover ruleをそのまま移動。

## 意図的に変更しなかった範囲

- DB schema / migration
- `public/api_v1.php` と公開API Response形式
- Authentication / Authorization / CSRF / SSRF / XSS / owner scope
- RSS 2.0 / RSS 1.0 / Atom Adapter
- Item identity / Cache / Lock / HTTP 304 / Retry / stale-if-error
- Bootstrap 4.1.3 / jQuery 3.3.1 / Drawer / iScroll / Font Awesome
- 4タブ、Feed CRUD、Stock、Settings、画面再読込方式
- Responsive、Accessibility、文言、最終デザイン

## コードの方針

新しいFramework、npm、Build tool、Service Container等は追加していません。既存のjQuery記法と関数中心の構造を残し、必要な範囲だけIIFE、初期化関数、request pending helperを追加しました。

## 次工程

M2-BではFeed通信と表示状態を整理し、Loading、0件、Error等を扱います。
