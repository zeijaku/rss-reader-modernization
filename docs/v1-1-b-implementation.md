# V1.1-B / R1 Implementation

## Scope

Version 1.1の最初の機能追加として、記事URLから既知のTracking Parameterを除去しました。

対象:

- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_term`
- `utm_content`
- `fbclid`
- `gclid`
- `dclid`
- `mc_cid`
- `mc_eid`
- `ref_src`

## Implementation

`app/url_normalizer.php`へ小さな共通処理を追加し、次の位置で使用しています。

1. Feed item linkをAPI表示用Payloadへ渡す前
2. Stock URLをDBへ保存する前
3. Item Identityを生成する前

一般Query Parameter、Path、Fragment、Parameter順序は維持します。`id`、`page`、`article`、`category`などは削除しません。

登録済みFeed URLは取得条件を含む可能性があるため、追加・更新・Feed Source scopeでは正規化しません。

Queryを`parse_str()`で組み直さず、元のParameter順序とencoded valueを保つ方式です。大きな抽象化やFramework追加はしていません。

## Security and compatibility

- Stock保存前の既存URL Validationを維持。
- Feed item linkの既存http/https Validationを維持。
- Clientからowner IDを受け取らない既存API contractを維持。
- Authentication、CSRF、owner scope、SSRF、XSS境界を変更しない。
- DB schema、Migration、設定項目は変更しない。
- Feed Cache clearは不要。

## Version

```text
Application Version: 1.1.0-dev.1
Application Label: RSS Reader Modernization V1.1-B / R1
```
