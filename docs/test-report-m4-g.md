# M4-G / R1 Test Report

## Result

M4-Gでは`1.0.0`のVersion boundary、Final Package、Checkpoint Package、Release Notes、Tag / GitHub Release手順を確認しました。

```text
Source tree regression       PASS 4936 / FAIL 0 / SKIP 7
Checkpoint ZIP regression    PASS 4936 / FAIL 0 / SKIP 7
Checkpoint package           PASS 779 / FAIL 0
Final release package        PASS 664 / FAIL 0
```

全Regressionは1回の実行時間上限を超えるため、到達点をLogで確認し、残り区間を重複なく分割して実行しています。各区間でFAIL 0を確認し、PASS / SKIPを合算しました。

## M4-G dedicated tests

```text
Final release builder        PASS 41
Documentation                PASS 120
Release process              PASS 16
```

## Syntax

```text
PHP syntax                    PASS 72 files / FAIL 0
JavaScript syntax             PASS 10 files / FAIL 0
Python AST parse              PASS 80 files / FAIL 0
```

Pythonは`__pycache__`を生成しないAST parseで確認しました。Release BuilderがBuild tree内の`__pycache__`を拒否するため、構文確認で不要な生成物を残さない方法を使用しています。

## Package details

```text
Checkpoint manifest targets  372 files
Checkpoint project files     373 files
Final release payload        211 files
```

Checkpoint用Manifest自身は自己Hashを持てないため、372対象 + Manifest自身1件で373ファイルです。

## Verification scope

- `APP_VERSION = 1.0.0`と表示Label。
- Final ZIPを同じSourceから2回生成し、byte単位のSHA-256一致。
- `package_status=FINAL`、`publishable=yes`。
- Preview / RC modeがFinal markerを拒否。
- 内部Manifest、外部SHA-256、ZIP CRC、unsafe path、重複Entry。
- Private設定、実DB、Backup、Log、Session、Cache、Evidence、入れ子ZIP、主要Secret patternの除外。
- M2-Gで固定したSecurity / DB / API / Runtime重要file Hashの継続確認。
- RC1とFinalの`app/`、`public/`、`config/`、`database/`を比較。

RC1からFinalへのRuntime差分は次だけでした。

```text
app/version.php              変更
public/                      変更なし
config/                      変更なし
database/                    変更なし
```

## SKIP / disclosed limits

自動RegressionのSKIPは既存の7件です。

- PDO SQLite driverがないためSQLite integrationをSKIP。
- SimpleXML / mbstringがないためlive parser / adapter matrixをSKIP。
- Chromium runtimeが完走しないためBrowser smokeをSKIP。

次のPrivateな実環境EvidenceはDistributionへ収録していません。

- 実MySQL接続とSchema verify。
- 実RSS 2.0 / RSS 1.0 / Atom取得。
- 実Browserでの8テーマ / Responsive / 操作確認。
- Backupから別DBへのRestore drillとRollback。
- GitHub hosted CI PHP 8.1 / 8.4 Job結果。

M4-F TemplateはHOLD / PENDINGのままであり、未実施項目をPASSへ変更していません。`publishable=yes`はVersionとPackage境界が正式Release用であることを示し、個別Hosting環境の動作確認まで自動的に保証する意味ではありません。
