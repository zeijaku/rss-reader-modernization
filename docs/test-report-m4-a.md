# M4-A / R1 test report

## Baseline

- Source checkpoint: `Frontend M2-G / R1`
- GitHub main commit: `78211b7f57dbf0e50778da45e0d9b3167d0e592a`
- Source ZIP SHA-256: `bdcd0c8eadbc00b014144aaa6ca4f9fbdb95c93409f32f36e8f49c1ff2b27a3d`

## Test environment

- PHP 8.4.16
- Node.js v22.16.0
- Python 3.13.5
- PDO driver: unavailable in this execution environment
- cURL / SimpleXML / mbstring: unavailable in this execution environment

## Source tree full regression

- PASS: 3263
- FAIL: 0
- SKIP: 7
- PHP syntax: 71 file PASS
- JavaScript syntax: 10 file PASS

## M4-A専用確認

- M2-G重要file 26件のSHA-256固定。
- GitHub main commit / source ZIP / source ManifestのBaseline記録。
- LICENSE、Third-party notice、license copyの存在確認。
- M3未完了扱いとM4-D〜Fへの確認移管。
- M4-A〜G工程、Release Gate、Release Blocker、公開物・配布物Inventory。
- config/local.php、.env、実DB、Log、Session、Cache、Lock、入れ子ZIP、unsafe pathの除外。

## SKIP

- PDO SQLite integration: PDO driverなし。
- SimpleXML / mbstringを使うlive Feed fixture解析。
- Chromium browser smoke: runtime dependency不足。

SKIPはPASSへ読み替えていない。実MySQL、通常Browser、実RSS / Atom providerはM4-Fで確認する。

## ZIP再展開

- Package / Manifest / unsafe path / nested ZIP / Runtime除外: PASS 312 / FAIL 0
- 再展開後全回帰: PASS 3263 / FAIL 0 / SKIP 7
- 再展開後PHP syntax: 71 file PASS
- Manifest対象: 296 file（checkpoint manifest自身を除外）

最終ZIPでも同じ検査を再実行した。
