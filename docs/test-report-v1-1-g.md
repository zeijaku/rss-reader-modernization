# V1.1-G / R1 Test Report

## V1.1-G focused checks

```text
Memo domain / validation / owner / transaction  PASS 28
Architecture / Security / UI contract           PASS 53
Schema / Migration / CLI contract               PASS 25
Frontend Memo runtime                           PASS 24
Feed / Clock / Memo Dashboard render            PASS 15
Real Chromium Memo / CRUD request               PASS 13
--------------------------------------------------------
Focused total                                    PASS 158
FAIL 0 / SKIP 0
```

## V1.1-B〜G local regression

```text
PASS 739
FAIL 0
SKIP 0
```

Tracking Parameter、新着状態、Dashboard Widget基盤、並び替え、Clock、Memoを区間分割して連続実行しました。

## Syntax checks

```text
PHP        57 files PASS
JavaScript  9 files PASS
Python     41 files PASS
Shell       7 files PASS
```

## Retained M2 render regression

```text
M2-C Dashboard render  PASS 19
M2-D Dashboard render  PASS 16
--------------------------------
Total                  PASS 35
```

SB-15 DocumentationとVersion markerも、GitHub mainの`.gitignore`と既存公開文書を検査用Copyへ戻した状態でPASSしています。

## Confirmed range

- Memo見出し1〜32文字、本文1〜4,000文字
- UTF-8、改行正規化、制御文字拒否、空本文拒否
- HTML風文字列のescapeとText-only表示
- MemoとWidgetの同時作成、変更、論理削除、Rollback
- owner scope、他User拒否、Client owner非採用
- Schema、Prefix、Migration再実行、CLI apply／verify契約
- active MemoとMemo Widgetの両方向orphan検査
- Drawer、空画面、追加／変更Modal、Responsive
- Feed／Clock／Memo混在表示と既存並び替え
- 実Chromiumでの改行表示、編集値、Create／Update／Delete Request
- Sanitized SQLを`.gitignore`で追跡可能にする例外

## Full runner limitation

再構成したV1.1-F配布Baselineには、GitHub Repositoryで管理しているSecure Baseline初期TestとM2-A／B／E／F Testの一部が含まれていません。`bash tests/run.sh`は不足する`tests/run.php`で停止するため、停止をApplication failureとは扱っていません。

V1.1-G適用後は、完全なGitHub作業Folderで`bash tests/run.sh`とGitHub Actions PHP 8.1／8.4を実行してください。

## HOLD

- 実MySQL 5.6／8.x／MariaDBへのMigrationと再実行
- phpMyAdminでのprefix変更、preflight、postflight
- 実DBでのMemo CRUD、複数User、複数Browser
- 4タブ、8テーマの全組合せ
- 実Touch端末／Pen
- BackupからのRestore、Migration前へのRollback
- GitHub Actions PHP 8.1／8.4

## Package verification

```text
Payload files               38（Manifestを含む）
ZIP entries                 39（root directoryを含む）
CRC                         PASS
Duplicate entries           なし
Path traversal              なし
Absolute path               なし
Private/runtime data        なし
Secret pattern              検出なし
Overlay manifest            一致
Deterministic build         PASS
```

同じSourceから2回生成し、Byte単位で同じZIPになることを確認しました。V1.1-F / R1の別CopyへZIPだけを適用した後も、Payload 38FileのByte一致と次の再検査を確認しています。

```text
V1.1-G focused checks       PASS 158 / FAIL 0 / SKIP 0
M2-C／M2-D Dashboard render PASS 35  / FAIL 0 / SKIP 0
Re-extracted total          PASS 193 / FAIL 0 / SKIP 0
```
