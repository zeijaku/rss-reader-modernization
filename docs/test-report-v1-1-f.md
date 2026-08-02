# V1.1-F / R1 Test Report

## V1.1-F focused checks

```text
Clock domain / validation / owner / transaction  PASS 29
Architecture / Security / UI contract           PASS 40
Frontend Clock / reorder runtime                 PASS 39
Mixed Feed / Clock Dashboard render              PASS 18
Real Chromium Clock / CRUD request               PASS 14
--------------------------------------------------------
Focused total                                    PASS 140
FAIL 0 / SKIP 0
```

## V1.1-B〜F local regression

```text
PASS 581
FAIL 0
SKIP 0
```

PHP構文、JavaScript構文、Tracking Parameter、新着状態、Dashboard Widget基盤、並び替え、Clockを連続実行しました。

## Secure Baseline〜M1 regression

不足していた過去TestをSecure Baseline／M1 Checkpointから補い、GitHub mainで更新された2つの静的Testだけを現行版へ合わせて実行しました。

```text
PASS 1429
FAIL 0
SKIP 6
```

SKIPはこの実行環境にないSimpleXML、mbstring、cURL、PDO driver等を必要とする範囲です。PASSへ読み替えていません。

## Retained M2 render regression

```text
M2-C Dashboard render  PASS 19
M2-D Dashboard render  PASS 16
--------------------------------
Total                  PASS 35
```

## Confirmed range

- Clock設定のDefault、境界値、不正値拒否
- 複数Clockと末尾追加
- owner scope、CSRF契約、Transaction、論理削除
- 12／24時間、日付、秒、ISO datetime
- 1本の共有Timer
- FeedとClockの混在表示・並び順・幅
- ClockタイトルのHTML escape
- Mouse／Touch／Keyboard並び替え契約
- 実Chromiumでの時刻即時表示、秒更新、日付OFF
- 実Chromiumでの追加・変更・削除Request
- DB Table／Migration追加なし

## Local baseline limitation

再構成した作業CopyにはGitHub mainのM2-A／B／E／F Test Fileが含まれていないため、全RunnerはM2-A開始位置で停止しました。Secure Baseline〜M1は上記結果で完走し、影響の大きいM2-C／D実RenderとV1.1-B〜Fは別に完走しています。Overlay適用後の完全なGitHub作業Folderで`bash tests/run.sh`とGitHub Actions PHP 8.1／8.4を実行してください。

## HOLD

- 実MySQL 5.6／8.x／MariaDBでのClock CRUD
- 実運用環境のBrowser timezone
- 実Touch端末／Pen
- 4タブ、8テーマの全組合せ
- 複数User／複数Browser
- GitHub Actions PHP 8.1／8.4
- BackupからのRestore

## Package verification

```text
Payload files               26
ZIP entries                 27（root directoryを含む）
CRC                         PASS
Duplicate entries           なし
Path traversal              なし
Absolute path               なし
Private/runtime data        なし
Secret pattern              検出なし
Overlay manifest            一致
Deterministic build         PASS
```

同じSourceから2回生成し、Byte単位で同じZIPになることを確認しました。V1.1-E / R7の別CopyへZIPだけを適用した後も次を再実行しています。

```text
V1.1-B〜F local regression  PASS 581 / FAIL 0 / SKIP 0
M2-C／M2-D Dashboard render PASS 35  / FAIL 0 / SKIP 0
Overlay source byte match   PASS 26 files
```
