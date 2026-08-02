# V1.1-E / R1 Test Report

## Local executable regression

```text
PASS 1882
FAIL 0
SKIP 6
```

内訳:

- Secure Baseline〜SB-13途中: PASS 572 / SKIP 2
- SB-13残区間〜SB-15: PASS 178 / SKIP 1
- M1-A〜M1-G: PASS 679 / SKIP 3
- V1.1-B〜V1.1-E: PASS 418
- 現在のM2-C / M2-D Dashboard実Render: PASS 35

一括RunnerはHTTP / process concurrency Testを含み実行時間が長いため、同じ順序を重複しない区間へ分けて完走した。SKIPはSimpleXML、mbstring、pdo_mysql、cURL等を必要とする実環境依存範囲で、PASSへ読み替えていない。

## V1.1-E focused checks

```text
Widget reorder / owner / conflict / transaction  PASS 22
Architecture / Security / UI contract            PASS 30
Frontend Keyboard / save / restore runtime       PASS 16
---------------------------------------------------------
Focused total                                     PASS 68
```

主な確認範囲:

- owner / tab scope
- 正のWidget ID、重複・欠落・置換拒否
- 最大200 Widget、JSON size上限
- 並び替え前後の集合一致
- stale Dashboard orderの409
- Transaction / Rollback
- 新規Feedの末尾追加
- Mouse Drag event contract
- Touch / Pen Pointer event contract
- Keyboard Arrow / Home / End
- CSRF付きAPI request
- 保存失敗時のDOM復元
- NEW、Feed表示、Responsive実Render
- V1.1-D postflight R2のDatabase選択Guard

## Syntax

```text
PHP syntax        PASS 81 files
JavaScript syntax PASS 24 files
Python compile    PASS 51 files
Shell syntax      PASS
```

## Local baseline limitation

この作業環境のV1.1-D Overlay再構成Copyには、GitHub main側にあるM2-A〜Fの全Test Fileが含まれていない。現在のWidget変更で影響が大きいM2-C / M2-D Dashboard実Renderは実行済み。M2-A〜F全体はOverlay適用後のGitHub mainで`bash tests/run.sh`とGitHub Actionsを実行する。

M2-G / M4-A〜GはVersion 1.0.0の歴史的Release Gateであり、V1.1開発Checkpointでは明示的にSKIPする。

## HOLD

- 実MySQL 5.6 / 8.x / MariaDBでの並び順保存
- 実Browser Mouse Drag
- 実Touch端末 / Pen
- 4タブ、8テーマ
- 複数Browserを使った同時変更409
- GitHub Actions PHP 8.1 / 8.4
- BackupからのRestore

## Package verification

```text
Payload files          27
ZIP entries            28（root directoryを含む）
CRC                    PASS
Duplicate entries      なし
Path traversal         なし
Absolute path          なし
Private/runtime data   なし
Secret pattern         検出なし
Manifest               一致
Deterministic build    PASS
```

ZIPをV1.1-Dの別作業Copyへ展開適用後、V1.1-E、V1.1-D、M2-C / M2-D実Render、PHP / JavaScript / Shell構文を再実行した。

```text
Re-extracted focused checks
PASS 189
FAIL 0
SKIP 0
```
