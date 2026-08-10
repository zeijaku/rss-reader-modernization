# Version 1.7.0 Test Report

## Baseline regression

V1.7-H/R4ではSecure BaselineからR4までをRunner順の区間分割で実行し、次を確認済みです。

```text
PASS  6,389
FAIL      0
SKIP     15
```

SKIPは不足Extension、Browser Runtime、過去正式Version専用Gateなどの環境／履歴依存です。

## Final-version focused regression

`APP_VERSION`を`1.7.0`へ正式化した後、V1.7-B～H/R4、Asset Cache、HTTP Header、Remember Token、Persistent Login、Grid、RSS件数、Calendar祝日、Release Gate、Documentation Linkを再実行しました。

```text
PASS 807
FAIL   0
SKIP   0
```

開発Checkpoint名を固定していた過去V1.7 Testは、機能条件を緩めず正式Version `1.7.0`を後続到達点として許可するよう更新しています。

## Syntax

```text
PHP        114 files PASS
JavaScript  27 files PASS
```

JavaScriptはMinified vendor Assetを除き`node --check`、PHPは全`.php`を`php -l`で確認しています。

## Package verification

初回正式Buildに対してBuilder／Verifierを実行しました。

```text
Complete package verifier : PASS 1,775 / FAIL 0
Runtime package verifier  : PASS 1,342 / FAIL 0
```

確認対象にはSHA-256 Sidecar、ZIP CRC、重複Entry、Unsafe path、Manifest全File、Version、Private設定、Runtime Data、Secret patternを含みます。

## Re-extract regression

Complete ZIPを空の別Directoryへ展開し、GitHubへ登録するSource形状からV1.7 Focused Regressionと構文確認を再実行しました。

```text
PASS 807
FAIL   0
SKIP   0
PHP        114 files PASS
JavaScript  27 files PASS
```

## Verification limits

- 実MySQL／MariaDBへのMigration 007／008適用は正式化段階では再実行していません。
- V1.7-H/R4までの実環境で`widget_height`とCalendar祝日表示は確認済みです。
- 内閣府CSVへのLive通信、HostingのApache module、全Browser／Themeの実機結果は配布物へPrivate Evidenceとして収録しません。
