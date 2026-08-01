# M2-G / R1 test report

## Baseline

M2-F / R1: PASS 2075 / FAIL 0 / SKIP 7。

## 追加した確認

- M2-A〜Gの実装Documentとtest reportが揃っていること。
- README、Roadmap、Version policy、ChecklistがM2完了状態で一致すること。
- current Version marker、Login / Dashboard表示、Healthcheckの整合。
- CSS / JavaScript / WebFont allowlistと旧Asset不存在。
- jQuery、Bootstrap、Bootswatch、Font Awesome、Drawer、iScrollのVersion / header。
- Feed action、CSRF、safe DOM、Responsive、Stock列、Drawer、ARIA等の主要invariant。
- Runtime directory、private config、npm / node_modules、秘密情報pattern、local Markdown link。
- ZIP Manifest、再展開後test、禁止file、unsafe path、入れ子ZIP。

## 環境上のSKIP

従来どおり、Build環境にPDO SQLite、SimpleXML、mbstringがない検査はSKIPする。
Chromium headless smokeはDBus runtime不足のためSKIPする。実MySQL、実Feed、Chrome / Edgeの目視確認は配置先で行う。

## Result

```text
M2-F / R1 baseline:
PASS: 2075
FAIL: 0
SKIP: 7

M2-G / R1:
PASS: 2247
FAIL: 0
SKIP: 7
PHP syntax errors: 0
JavaScript syntax: PASS
```
## 配布物確認

```text
Manifest target: 274 files
ZIP files: 275 files（Manifestを含む）
Project root: 1
Missing: 0
Extra: 0
Hash mismatch: 0
Unsafe path: 0
Nested ZIP: 0
Forbidden / Runtime file: 0

ZIP再展開後:
PASS: 2247
FAIL: 0
SKIP: 7
```

M2-F / R1との比較では、`app/feed/`、公開API、Authentication、Session、HTTP fetch、Validation、DB、config、Dashboard HTML / CSS / JavaScriptは同一。M2-GのApplication変更はvisible Version markerのみ。
