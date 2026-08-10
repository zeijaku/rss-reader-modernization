# Version 1.8.0 Test Report

実施日: 2026-08-07 JST

## Segmented Full Regression

V1.8-Fでは、毎StageでFull Testを繰り返さず、正式化時のみRunnerを4区間へ分けて実行しました。途中で止まった箇所は原因を確認し、既にPASS済みの区間を不要に再実行せず、修正対象Testとその続きだけを再開しています。

```text
Segment 1  Secure Baseline ～ M2      PASS 2,169 / FAIL 0 / SKIP 7
Segment 2  Version 1.1               PASS 1,535 / FAIL 0 / SKIP 1
Segment 3  Version 1.2 ～ 1.5        PASS 2,025 / FAIL 0 / SKIP 4
Segment 4  Version 1.6 ～ 1.8        PASS   732 / FAIL 0 / SKIP 3
---------------------------------------------------------------
Total                                PASS 6,461 / FAIL 0 / SKIP 15
```

SKIPは、過去正式Version専用Release Gate、PDO SQLite Driver不在などの環境／履歴依存です。

Regression中に見つかった停止要因はApplication本体の新規不具合ではなく、以下の過去Test前提でした。

- Stockを4列Cardとみなす旧Responsive Test
- Calendar Asset Versionを過去Checkpointへ固定したTest
- V1.4～V1.7の「later」を1.7までの固定列挙で判定していたTest
- V1.3-C Fake PDOがV1.8-EのTask target Query／COUNT Queryを知らないFixture不足
- V1.8-B～E Testが開発Markerのみを許可し正式`1.8.0`を許可していない条件

機能条件は緩めず、後続正式VersionをSemVer上のlaterとして扱うTestへ整理しました。

## Version 1.8 Focused Regression

正式`APP_VERSION = 1.8.0`へ切り替えた後、Stock解除、検索／Sort、Pagination、Actions／Domain／Compact UIとRelease／Documentationを再確認しました。

```text
PASS 488
FAIL   0
SKIP   1
```

SKIP 1件はPDO SQLite Driver不在によるV1.8-B実DBTestです。Ownership、CSRF、論理削除、検索Bind、Pagination SQLは他のAPI／Static／Fake PDO Testで確認しています。

## Syntax

```text
PHP         121 files PASS
JavaScript  28 files PASS
```

JavaScriptはMinified vendor Assetを除き`node --check`、PHPは全`.php`を`php -l`で確認しています。

## V1.8-C R2 real-environment fix

V1.8-C/R1では、MySQL Native Prepareで同一Named Placeholderを2回利用していたため、検索語入力時に500となる問題が実機確認で判明しました。R2でTitle／URL用Placeholderを分離し、`AI`をRegression入力として確認済みです。DB変更はありません。

## Package verification

Preliminary正式BuildでPackage構造を確認しました。最終Documentation反映後に同じBuilderで再Build／再Verifyします。

```text
Complete package verifier : PASS 1,869 / FAIL 0
Runtime package verifier  : PASS 1,385 / FAIL 0
```

## Verification limits

- V1.8はDB Table／Column／Index／Migrationを追加していません。実MySQLへのSchema変更作業はありません。
- PDO SQLite Driverがこの実行環境に無いため、SQLiteを使う一部TestはSKIPです。
- Hosting固有Apache設定、全Browser／全実機の網羅確認は自動Regressionの範囲外です。
- Userによる実環境確認済みのV1.8-B～E操作結果も正式化判断へ含めています。
