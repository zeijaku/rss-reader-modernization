# Version 1.2.0 finalization

## Baseline

- Direct baseline: `RSS Reader Modernization V1.2-D / R5`
- Development Version: `1.2.0-dev.4`
- Final Version: `1.2.0`

## Scope

V1.2-A～DとR2～R5で実装・確認済みの内容をVersion 1.2.0として確定します。新機能、DB変更、追加Refactorは行いません。

Application Runtimeの変更は`app/version.php`だけです。その他はRelease Notes、README、配置手順、Package Builder／Verifier、Release Gate Test、Manifest用文書の更新です。

## Database

- Table追加: なし
- Column追加: なし
- Migration: なし
- SQL実行: 不要
- `config/local.php`: 変更なし
- `.htaccess`: 変更なし

## Test policy

正式Release前のためFull Testを1回実行します。Full Test後に行うTest結果文書とManifest更新は、Documentation Link、Release Gate、Package Verifyだけを再確認し、Application全回帰は繰り返しません。
