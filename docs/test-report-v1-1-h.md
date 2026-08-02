# V1.1-H / R1 Test Report

## V1.1-H focused checks

```text
Task domain / validation / owner / transaction  PASS 52
Architecture / Security / UI contract           PASS 52
Schema / Migration / CLI contract               PASS 34
Frontend Task runtime                           PASS 35
Feed / Clock / Memo / Task Dashboard render     PASS 19
Real Chromium Task / CRUD request               PASS 20
--------------------------------------------------------
Focused total                                    PASS 212
FAIL 0 / SKIP 0
```

## V1.1-B〜H local regression

```text
PASS 951
FAIL 0
SKIP 0
```

Tracking Parameter、新着状態、Dashboard Widget基盤、並び替え、Clock、Memo、Taskを区間分割して実行しました。

## Syntax checks

```text
PHP        59 files PASS
JavaScript 10 files PASS
Python     45 files PASS
Shell       8 files PASS
```

## Retained M2 render regression

```text
M2-C Dashboard render  PASS 19
M2-D Dashboard render  PASS 16
--------------------------------
Total                  PASS 35
```

## Confirmed range

- Task Widget見出し1〜32文字、Task名1〜128文字
- 任意期限の厳密な`Y-m-d`確認
- 優先度low／normal／highのallowlist
- 完了／未完了切替と完了日時
- 1Widget最大100件
- Task WidgetとTask項目のowner scope、他User拒否、Client owner非採用
- Transaction、Row Lock、論理削除、Rollback
- Widget削除時のTask項目同時論理削除
- HTML風文字列のescapeとText-only表示
- Schema、Prefix、Migration再実行、CLI apply／verify契約
- active TaskとTask Widgetのorphan検査
- Drawer、空画面、追加／変更Modal、Responsive
- Feed／Clock／Memo／Task混在表示と既存並び替え
- 実Chromiumでの追加、編集、完了切替、削除Request

## Full runner limitation

再構成したV1.1-G配布Baselineには、GitHub Repositoryで管理しているSecure Baseline初期TestとM2-A／B／E／F Testの一部が含まれていません。不足Testを過去Checkpointから補った検査用CopyではSB-12まで進行しましたが、実DB driverを必要とする旧Testで長時間停止したため、完走をPASSとは扱っていません。

V1.1-H適用後は、完全なGitHub作業Folderで`bash tests/run.sh`とGitHub Actions PHP 8.1／8.4を実行してください。

## HOLD

- 実MySQL 5.6／8.x／MariaDBへのMigrationと再実行
- phpMyAdminでのprefix変更、preflight、postflight
- 実DBでのTask CRUD、複数User、複数Browser
- 4タブ、8テーマの全組合せ
- 実Touch端末／Pen
- BackupからのRestore、Migration前へのRollback
- GitHub Actions PHP 8.1／8.4

## Package verification

```text
Payload files               39（Manifestを含む）
ZIP entries                 40（root directoryを含む）
CRC                         PASS
Duplicate entries           なし
Path traversal              なし
Absolute path               なし
Private/runtime data        なし
Secret pattern              検出なし
Overlay manifest            一致
Deterministic build         PASS
```

同じSourceから2回生成し、Byte単位で同じZIPになることを確認しました。V1.1-G / R1の別CopyへZIPだけを適用した後も、Payload 39FileのByte一致と次の再検査を確認しています。

```text
V1.1-H focused checks       PASS 212 / FAIL 0 / SKIP 0
V1.1-G regression           PASS 158 / FAIL 0 / SKIP 0
M2-C／M2-D Dashboard render PASS 35  / FAIL 0 / SKIP 0
Re-extracted total          PASS 405 / FAIL 0 / SKIP 0
```
