# Version 1.2.0 Test Report

## Test level

- Level: Full
- Baseline: V1.2-D / R5
- Target Version: 1.2.0
- DB / SQL / Migration: 変更なし

## Full regression

実行環境の1回あたりの時間上限により、`tests/run.sh`を次の範囲へ分割して最後まで実行しました。

1. Secure Baseline ～ M2-C
2. M2-D ～ M2-F
3. V1.1-B ～ V1.1-J
4. V1.2-A ～ V1.2-D / R5
5. Version 1.2 Release Gate

同一範囲の重複を除いた集計です。

```text
PASS : 4,268
FAIL : 0
SKIP : 10
```

## SKIP

- PDO SQLite integration: 実行環境にDriverなし
- SimpleXML / mbstringを必要とするLive parser系: 実行環境にExtensionなし
- M2-F Chromium smoke: 実行環境のChromium Runtime dependency不足
- M2-G / M4-A～G: Version 1.0専用のHistorical Release Gate
- V1.1-K: Version 1.1.0専用のHistorical Release Gate

Version 1.2固有のAuthentication、Feed article、Search Feed、Article Actions、新着Bell、Release Gateは実行済みです。

## Issues detected during finalization

### Historical layout test

M2-Dの旧Testが記事左右列を両方44pxと固定判定していました。R2～R4で意図的に変更された現行仕様へTestを同期しました。

- Article Actions列: 36px
- Summary列: 32px
- Summary Button: 44pxを維持し、左へ12px配置

Application CSSは変更していません。

### Complete package manifest

Complete ZIP BuilderがRootに残る旧`SOURCE_MANIFEST.sha256`をPayloadへ取り込んだ後、新Manifestへ置き換えていたため、VerifierのFile setと一致しない問題を検出しました。

Builderで旧`SOURCE_BUILD.txt`と旧`SOURCE_MANIFEST.sha256`を収集対象から除外し、Build時に新規生成するよう修正しました。

### Release cleanup

存在しないRuntime Directoryを`find`へ一括指定するとRelease Gateが停止するため、存在するDirectoryだけを掃除するようTest Runnerを調整しました。

## Application changes caused by tests

- 機能修正: なし
- Application変更: `app/version.php`の正式Version化のみ
- DB変更: なし

## Syntax verification

```text
PHP    99 files PASS
JavaScript 19 files PASS
Python 137 files PASS
Shell   11 files PASS
```

PHPは全Fileを`php -l`、JavaScriptは`node --check`、Pythonは`compileall`、Shellは`bash -n`で確認しました。

## Package verification

最終文書を含むPackageを2回生成し、同一SHA-256になることを確認しました。

```text
Complete source ZIP : 604 files / verifier PASS
Runtime ZIP         : 321 files / verifier PASS 994 checks
Deterministic Build : PASS
```

確認範囲はSHA-256、ZIP CRC、重複Path、Absolute／Parent Traversal、Manifest、Private設定、Runtime Data、Version metadata、Secret patternです。

別Directoryへ再展開したComplete ZIPでもPHP 99、JavaScript 19、Python 137、Shell 11 Fileの構文を確認しました。`config/local.php`と生成Runtime Dataは含まれず、R5と比較して`public/`、`database/`、`config/`、`.htaccess`、`app/`の`version.php`以外が一致しています。
