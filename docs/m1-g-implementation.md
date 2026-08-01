# M1-G — Fetch state / Retry / stale-if-error

Build: `RSS Engine M1-G / R1`

## 目的

M1-Gは、Feed提供元の一時障害時に同じ失敗を短時間で繰り返さず、最後に正常確認できたFeedを限定的に再利用する工程です。M1-EのServer-side cacheとURL単位Lock、M1-FのETag / Last-Modified / HTTP 304を維持したまま、Fetch state、Retry-After、段階的Backoff、stale-if-errorを追加します。

DB、Frontend、公開API、Stock、Parser、Adapter、Item identityは変更しません。

## 実装方針

過度な抽象化を避けるため、追加した中心ファイルは小さな関数群を持つ `app/feed/feed_retry.php` だけです。新しいRepository、Factory、Queue、Worker、Interface階層は追加していません。

状態ファイルの読み書きは、既存の `FeedCache` に小さな処理を追加しています。取得全体の判断は引き続き `FeedFetchService` で追える構成です。

## Fetch state

Feed URLごとに次のファイルを使用します。

```text
var/cache/feed/feed-v1-<Feed URL SHA-256>.state.json
```

状態には次だけを保存します。

```text
last_attempt_at
last_success_at
last_result
last_http_status
last_error_code
consecutive_failures
next_retry_at
```

状態ファイルにはFeed URL、query token、Feed本文、cURLの詳細メッセージを保存しません。`source_key` は検証済みFeed URLのSHA-256です。

状態JSONは最大16KB、schema 1、allow-listされたresult、0〜599のHTTP status、64文字以下のerror codeとして検証します。壊れたJSON、別URLのstate、未来へ大きくずれた時刻、symlink targetは拒否します。

書込みはCacheと同様、一時ファイルからのrenameで行います。Directoryはbest-effort `0700`、fileはbest-effort `0600`です。

## エラー分類

### transient

次は一時的な可能性があるものとして扱います。

- timeout
- DNS失敗
- 一般的なtransport error
- 空Response
- HTTP 408 / 425 / 429 / 500 / 502 / 503 / 504
- 以前は正常だったFeedの一時的なParse失敗

### permanent

HTTP 400 / 401 / 403 / 404 / 405 / 410など、短時間で改善しにくい失敗はpermanentとして扱います。stale Feedでは隠しませんが、同じHTTP取得を連打しないよう15分待機します。

### security

次はstale表示やBackoffで隠しません。

- invalid URL
- default port以外
- private / special-use address
- invalid redirect
- TLS verification failure
- response size超過

Security errorはstateへ短いcodeだけを記録しますが、`next_retry_at`は設定しません。

## Backoff

同じPHP request内でsleepして再試行する処理は追加していません。次回アクセス時に外部取得してよい時刻を確認します。

```text
1回目: 60秒
2回目: 300秒
3回目: 900秒
4回目以降: 3600秒
```

最大値は `APP_FEED_RETRY_MAX_DELAY_SECONDS` で制限します。成功したHTTP 200または304では失敗回数と次回試行時刻をリセットします。

## Retry-After

HTTP 429または503で、提供元が有効な `Retry-After` を返した場合はApplication側Backoffより優先します。

対応形式:

- delta-seconds
- RFC 7231 HTTP-date

CR/LF、NUL、制御文字、負数、小数、不正date、長すぎる値は拒否します。値はApplication側の最大待機時間へ丸めます。Redirect途中の `Retry-After` は最終Responseへ引き継ぎません。

## stale-if-error

一時エラーで、現在のParserが読める期限切れCacheがある場合だけstale Feedを返します。

初期値:

```text
APP_FEED_STALE_IF_ERROR_ENABLED=true
APP_FEED_STALE_MAX_AGE_SECONDS=86400
```

Cacheの年齢は最後にHTTP 200または304で正常確認した `validated_at` から計算します。初期値では24時間以内だけ利用します。

次の場合はstaleを利用しません。

- Security error
- permanent error
- Cache本文が現在のParserで読めない
- 最大年齢を超えている
- Cacheまたはstale-if-errorが無効

公開APIにはstale状態、次回試行時刻、error codeを追加しません。画面は従来のFeed項目だけを受け取ります。

## 同時アクセス

URL単位Lockの内側でFetch stateを再確認します。5processが同時に期限切れCacheへアクセスし、1件目がHTTP 503になった場合、1件だけが外部通信と失敗状態更新を行います。残りはLock待機後にBackoff stateとstale Cacheを利用します。

## 設定

```text
APP_FEED_RETRY_ENABLED=true
APP_FEED_RETRY_MAX_DELAY_SECONDS=3600
APP_FEED_STALE_IF_ERROR_ENABLED=true
APP_FEED_STALE_MAX_AGE_SECONDS=86400
```

`APP_FEED_RETRY_ENABLED=false` ではstate/backoffを停止できます。`APP_FEED_STALE_IF_ERROR_ENABLED=false` ではstate/backoffを維持したままstale表示だけを停止できます。

## 変更していない範囲

- Database schema / migration
- Frontend JavaScript / HTML / CSS
- Authentication / Session / CSRF
- FeedSource / Parser / RSS・Atom Adapter
- Item identity
- Stock保存形式
- 公開API response shape
- Itemの順番・件数・重複処理

## M1完了点

M1-Gで、現行のM1 Source / RSS Engine計画は完了です。

```text
M1-A Fetcher / Parser / Normalized Item
M1-B FeedSource
M1-C RSS / Atom Adapter + Date normalization
M1-D Item identity
M1-E Server-side cache + duplicate Fetch suppression
M1-F ETag / Last-Modified / HTTP 304
M1-G Fetch state + Retry / stale-if-error
```

次工程はM2 Frontendとして別に扱います。
