# SB-12 R2 Atom Link Hotfix Test Report

Build: `Secure Baseline SB-12 / R2`  
Base: `Secure Baseline SB-12 / R1`

## Defect

実環境でAtom Feedのentry titleは表示される一方、article URLが空となりfrontendがanchorを生成しない事象が確認された。

R1の`feed_link()`はdefault namespace viewとarray-style attribute accessを組み合わせており、Atom `link href` の抽出経路が実Feedに対して十分検証できていなかった。

## R2 tests

- Link relation selection unit tests
  - alternate text/html > self
  - alternate without type > self
  - relation-less link > self
  - self fallback
  - blank/non-string rejection
  - case-insensitive rel/type
- API payload regression
  - Qiita型https article URLを保持
  - Publickey型https article URLを保持
  - normal http article URLを保持
  - javascript URLは引き続きreject
- Static extraction audit
  - direct-child XPath with `local-name()`
  - `attributes()` usage
  - text-node RSS link fallback
  - `<url>` fallback
  - frontend anchor generation retained
- Synthetic XML fixtures
  - Qiita shape
  - Publickey shape
  - RSS 2.0 text link shape

## Environment limitation

この実行環境にはSimpleXML / mbstringがないため、PHPによるfixture XMLの実parse部分は条件付きSKIP。Link選択ロジック、API URL validation、static extraction経路、既存全回帰試験は実行する。

配置先にはSimpleXML / mbstringが存在するため、CHECKLIST記載のQiita/Publickey実Feed確認をrelease gateとする。

## Full regression result

Source tree final regression before packaging:

```text
PHP syntax: 33 files OK
PASS: 504
FAIL: 0
SKIP: 2
```

SKIPはこのsandbox固有で、PDO SQLite integration driver不存在、およびSimpleXML/mbstring不存在によるlive XML fixture parse。その他のSB-00〜12回帰、link selection、API URL validation、static extraction、HTTP smoke、SSRF/XSS/session/auth試験は通過した。
