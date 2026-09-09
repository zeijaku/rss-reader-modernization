# V1.33-H RC2 Test Report

実行日: 2026-09-09

対象: `RSS Reader Modernization 1.33.0-RC2`

## Summary

| Gate | PASS | FAIL | SKIP | Result |
|---|---:|---:|---:|---|
| RC2 Asset loader behavior | 22 assertions | 0 | 0 | PASS |
| V1.33 Python static/contract + version/H contract | 240 assertions | 0 | 0 | PASS |
| V1.33 Calendar JavaScript behavior/DOM | 130 assertions | 0 | 0 | PASS |
| RC2関連JavaScript / runner syntax | 13 commands | 0 | 0 | PASS |
| Current Drawer / Account Security static regression | 6 suites | 0 | 0 | PASS |
| V1.33 HTTP integration | 0 | 0 | 2 tests | SKIP: PHP CLI unavailable |
| Migration 025 live MariaDB | 0 | 0 | 1 test | SKIP: MariaDB tools unavailable |
| Production Browser | 0 | 0 | 1 gate | SKIP: Browser runtime unavailable |
| RC2 overlay package manifest / source parity | 5 files | 0 | 0 | PASS |
| Deterministic rebuild | 1 | 0 | 0 | PASS |
| Clean-room extraction / packaged JS syntax | 1 gate | 0 | 0 | PASS |

実行可能だったTestにFAILはありません。

## Focused Calendar coverage

- Common range limit、Occurrence identity、deduplication、stale response拒否。
- Owner Scope、CSRF、request size limit、Session lock release、JSON defensive escaping。
- Migration 025の加算性、prefix validation、idempotency contract、schema parity。
- Occurrence単位update／cancel／restore、optimistic revision、409 conflict、series guard。
- 5色、既存色互換、Light／Dark style contract。
- 複数日`single`／`start`／`middle`／`end`、週・月境界、overlap lane。
- 日／週／月range計算、leap day、年境界、00:00、終了時刻なし、同時刻、overlap。
- Day/Week DOM、24 hour lane、compact card、responsive toolbar、ARIA state。
- RC version、asset cache key、Release warning、future Copy／Drag & Drop記録。
- JSの完全な宣言順、次Dependencyを先に開始しないこと、失敗時の1回だけの再試行。
- CSSの4本単位の開始、Cascade順保持、同位置での1回だけの再試行。
- Loaderが`api_v1.php`、`$.ajax`、`fetch()`を使用せず、更新系Requestを再送しないこと。

## Security / regression coverage

Current contractからDrawer、Account Security、Session Registry、Authentication Audit、Step-up、V1.32 release contractを抽出実行し、すべてPASSしました。RC2 LoaderはHTML sink、`eval`／`new Function`、Application API再送を追加していません。

V1.33 RC2によるPublic PHP endpoint、SQL、Config、認証処理の変更はありません。

## SKIP details

このBuild環境にはPHP CLI、PHP 8.1、PHP 8.4、MariaDB/MySQL client/server、Browser runtimeがありません。そのため次は未実施であり、PASS扱いしていません。

- `tests/run-current.sh` full suite。
- `tests/run-current-features.sh` full suite。
- PHP syntax lintとPHP unit/integration tests。
- V1.33 range／exception HTTP integration（Test自身は明示SKIP）。
- Migration 025の実MariaDB新規適用／再実行／別prefix／Data保持。
- PHP 8.1／PHP 8.4 matrix。
- Production Browser、全Theme、320–1280px、実DB、Error log。
- GitHub Actions、main SHA、formal package、immutable tag／Release gate。

Current runner外の古いCheckpoint専用Testも参考実行しましたが、過去Versionだけを許可する固定条件、既に整理済みの旧Apply Note不足、PHP CLI呼出しによりFAIL／Errorとなるものがありました。RC2の変更で発生したFAILではなく、activeなCurrent／V1.33 Gateには含めていません。これらを通すための本番コード退行は行っていません。

## Package result

- File: `rss-reader-v1.33.0-rc2-production-app-public.zip`
- SHA-256: `48292d0e58c4ec60355a3c15403627ebf496e9e7ea2fd3c9adf0dd6f7879e3f7`
- Status: `RELEASE_CANDIDATE`
- Publishable: `no`
- Payload: RC1→RC2用の4 production files、Apply Note、内部Manifest。Top-levelの`app/`／`public/`を上書き可能。
- Excluded: Database、Migration、Config、tests、`.github`、runtime data、database dump、archive、Secret。
- Deterministic rebuild: byte-identical PASS。

## Remaining Final Gate

1. 本番環境へMigration 025を未適用の場合だけ適用する。
2. `docs/v1-33-h-finalization.md`の順序でRCを確認する。
3. PHP 8.1／8.4の`tests/run-current.sh`と`tests/run-current-features.sh`をPASSさせる。
4. GitHub Release branch／PR／ActionsでSecurity、Migration、Package、Clean-roomをPASSさせる。
5. その後だけ`1.33.0`へ正式化し、main／`v1.33.0`／GitHub Releaseへ進む。
