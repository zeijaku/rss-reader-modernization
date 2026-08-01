# M2-F / R1 test report

## Baseline

M2-E / R2: PASS 1997 / FAIL 0 / SKIP 6。

## 追加した確認

- jQuery 3.7.1 full buildとAJAX API。
- old jQuery参照の不存在とscript読込順。
- Bootstrap 4.1.3 / Bootswatch 4.1.3のVersion整合。
- Bootstrap Modal / Collapse / Popover pluginの存在。
- Drawer / iScrollのVersionとplugin境界。
- Font Awesome 6.7.2の旧class alias、使用icon、WebFont参照。
- 8テーマ、CSS / JS / fontのHTTP 200。
- Chromium headless smokeのtest harnessを追加。Build環境はDBus socketがなく起動が安定しないためSKIPし、配置先で目視確認する。
- PowerShell cleanup helperのASCII / CRLF / scope。
- M2-EまでのAsset cleanup、M2-A〜D、M1、Secure Baselineの全回帰。

## 環境上のSKIP

従来どおり、Build環境にPDO SQLite、SimpleXML、mbstringがない検査はSKIPする。実MySQL、実Feed、各Browserの目視確認は配置先で行う。

## Result

```text
M2-E / R2 baseline:
PASS: 1997
FAIL: 0
SKIP: 6

M2-F / R1:
PASS: 2075
FAIL: 0
SKIP: 7
PHP syntax errors: 0
JavaScript syntax: PASS
```

追加のSKIP 1件はChromium runtimeに必要なDBus socketがBuild環境にないため。test harness自体は配布し、配置先でBrowser確認を行う。
