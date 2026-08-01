# M2-B / R1 — Feed表示処理整理

## 目的

M2-Aで外部Asset化した `public/js/dashboard.js` のうち、Feed取得とDOM描画が一つの関数へ集中していた部分を整理します。M2-A / R1をBaselineとし、Legacy版や以前のZIPへ戻していません。

大きな画面変更は行わず、取得途中、0件、取得失敗など、これまで空白または一つのerror文言で扱っていた状態を明示します。

## 変更内容

- Feed取得、状態message、Channel title、Item row描画を関数へ分離。
- Feed cardへ次の状態を付与。
  - `loading`
  - `ready`
  - `empty`
  - `error`
- PHP初期描画時にも「読み込み中...」を表示し、JavaScript実行前の空白をなくした。
- 0件FeedはChannel titleを残し、「記事はありません」を表示。
- Timeout、404、502等の取得失敗はFeed card単位でerror表示し、他のFeed描画を止めない。
- API Responseが不足、不正形式、配列そのもの等の場合は安全側でerror表示。
- Channel / Item titleが空の場合は「タイトルなし」を表示。
- 配列内の不正Itemを無視し、有効なItemを最大5件まで表示。
- 長いItem titleは絵文字のsurrogate pairを途中で分断せず省略。
- Frontend側でもFeed linkをhttp / httpsへ限定。
- 同じFeed cardが通信中の場合は重複Requestを開始しない。
- `favicon.png` をheadから明示的に参照し、HTTPS環境で404 ErrorDocumentを経由するMixed Content警告を回避。

## 表示状態

| 状態 | 表示 |
|---|---|
| loading | 読み込み中 / フィードを読み込んでいます |
| ready | Channel titleと最大5件の記事 |
| empty | Channel title / 記事はありません |
| error | コンテンツを取得出来ませんでした / 原因別の短い案内 |

HTTP 502は、登録Feedが取得不能またはFeedとして解析できない場合に既存APIが返す契約です。M2-Bではその契約を変えず、該当cardだけerror表示します。

## Security

Feed textは引き続き `.text()` で挿入し、HTML文字列連結、`.html()`、`innerHTML` は使用していません。

外部linkは次を維持しています。

- server-side `api_safe_feed_payload()`
- Frontendのhttp / https確認
- `target="_blank"`
- `rel="noopener noreferrer"`

Feed URLはBrowserから送らず、`feed.fetch` にはowner-scoped `content_id` だけを送信します。

## 意図的に変更しなかった範囲

- DB schema / migration
- `config/local.php` の項目
- `public/api_v1.php` と公開API Response形式
- Authentication / Authorization / CSRF / SSRF / owner scope
- RSS 2.0 / RSS 1.0 / Atom Adapter
- Item identity / Cache / Lock / HTTP 304 / Retry / stale-if-error
- Feed表示上限5件
- 4タブ、Feed CRUD、Stock、Settings
- Bootstrap 4.1.3 / jQuery 3.3.1 / Drawer / iScroll / Font Awesome
- Responsive layout、HTML semantic、Keyboard / ARIA、最終デザイン

## コードの方針

新しいClass、Frontend Framework、npm、Build toolは追加していません。既存の関数中心のjQuery記法を残し、Feed処理内だけを追いやすい単位へ分けています。

## 次工程

M2-CではHTML構造と操作要素を見直し、Keyboard、Focus、Label、ARIA等のAccessibility対応を進めます。
