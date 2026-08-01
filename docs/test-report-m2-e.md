# M2-E / R2 Test Report

## Baseline

- Baseline: Frontend M2-D / R2
- PASS: 1837
- FAIL: 0
- SKIP: 6

## M2-E確認範囲

- 保持CSS / JavaScriptのallowlist
- Theme resolverの8テーマ
- HTML / PHPからの直接Asset参照
- CSSからのFont参照
- Source Map参照
- 使用Icon classのFont Awesome定義
- vendor License / Version header
- 廃止directory / fileの不存在
- cleanup PowerShellの安全境界
- CSS / JS / Font / faviconのHTTP 200
- M2-A〜DとBackend全回帰

## Project最終結果

```text
PASS: 1997
FAIL: 0
SKIP: 6
PHP syntax checked: 71 files
PHP syntax error: 0
JavaScript syntax: PASS
```

M2-E専用・拡張test:

- Asset inventory: 79 checks
- Cleanup helper: 64 checks（Windows PowerShell 5.1 encoding regressionを含む）
- Public HTTP smoke: 保持Theme / CSS / JavaScript / WOFF2のHTTP 200を追加
- Documentation / Version gateをM2-Eへ更新

## Asset整理結果

```text
public files: 127 -> 39
public bytes: 15,721,978 -> 4,880,574
removed files: 88
removed bytes: 10,841,404
reduction: 約69.0%
```

`popper.min.js` はBaselineにSource Map本体が存在しなかったため、実行コードを変えず末尾のMap hintのみ削除した。そのほかの保持vendor fileはVersionとLicense headerを維持している。


## R2 correction

R1のcleanup helperはUTF-8 BOMなしで日本語messageを含み、Windows PowerShell 5.1ではANSIとして誤解釈されParser Errorになった。R2ではScriptをASCIIのみ・CRLFへ変更し、実行前にParser Errorとなる回帰を防止した。R1のParser Errorでは削除処理は開始されない。

## ZIP再展開後

```text
PASS: 1997
FAIL: 0
SKIP: 6
PHP syntax checked: 71 files
Manifest entries: 267
Missing / Extra / Hash mismatch: 0 / 0 / 0
Forbidden files: 0
```

## 視覚確認

M2-EではMarkup、Dashboard CSS、Application JavaScriptを変更していない。Fake PDO render、既存M2-C / M2-D test、HTTP smokeで回帰を確認した。配置先では8テーマ、Font Awesome icon、Drawer、Modal、Browser Consoleの404を目視確認する。

## SKIP

Build環境にPDO SQLite、SimpleXML、mbstringがないことによる既存6項目。
