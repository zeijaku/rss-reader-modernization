# M1-G Test Report

Build: `RSS Engine M1-G / R1`

## 結果

M1-G専用試験では、HTTP / Retry helper、Fetch state / stale-if-error、Architecture、5process concurrencyを実行しました。

```text
M1-G HTTP / Retry executable: 37 PASS
M1-G resilience executable:   40 PASS
M1-G architecture / static:   79 PASS
M1-G process concurrency:     14 PASS
M1-G dedicated total:        170 PASS
```

Source treeの全回帰結果:

```text
PASS: 1423
FAIL: 0
SKIP: 6
PHP syntax: 71 files / 0 error
```

最終ZIPを別directoryへ再展開した後の全回帰結果:

```text
PASS: 1423
FAIL: 0
SKIP: 6
PHP syntax: 71 files / 0 error
```

Source treeとZIP再展開後で結果は一致しました。Package manifestは自己参照となる `docs/package-manifest.txt` を除く322ファイルを照合し、missing 0、extra 0、hash mismatch 0でした。M1-Fとの保護境界比較は146ファイル、差分0です。

## HTTP / Retry-After

確認項目:

- delta-seconds
- RFC 7231 HTTP-date
- CR/LF / NUL / control character拒否
- 負数・小数・不正date・長大値拒否
- 過去date無視
- 最大待機時間へのcap
- HTTP 429 / 503だけでRetry-Afterを使用
- Redirect途中のRetry-Afterを最終Responseへ持ち越さない
- 同じRequest内で即時Retryしない

## エラー分類

確認項目:

- timeout / DNS / transport / HTTP 408・425・429・5xxはtransient
- HTTP 404はpermanent
- TLS / private address / response size超過はsecurity
- Parse失敗は古い正常Feedがある場合だけbounded stale候補
- Security errorはstale表示しない
- Security errorへnext retryを設定しない

## Fetch state

確認項目:

- 初回200でsuccess state作成
- 304でnot_modifiedとsuccess time更新
- failure count加算
- 成功時のfailure count reset
- 60秒 / 300秒 / 900秒 / 3600秒Backoff
- 503 Retry-After優先
- permanent errorの15分待機
- Backoff中は外部通信しない
- URL / query token / Feed本文 / transport message非保存
- state keyのURL scope
- invalid JSON / checksum相当の構造不正 / future timestamp拒否
- symlink target拒否
- atomic rename
- private file permission

## stale-if-error

確認項目:

- timeout時に最後の正常Feedを利用
- HTTP 503時に利用
- Parse失敗時に利用
- Backoff中に外部通信せず利用
- 最大age境界で利用
- 最大age超過で拒否
- HTTP 404で利用しない
- TLS errorで利用しない
- stale機能OFF
- Retry state OFF
- Cache OFF
- Parser / Adapter / Item identity経路を維持

## 同時実行

期限切れCacheへ5processから同時アクセスし、HTTP 503を返すsynthetic transportで確認しました。

```text
upstream Fetch:       1
successful responses: 5
stale responses:      5
state update:         1
consecutive_failures: 1
Retry-After applied:  300 seconds
state temp leftovers: 0
```

Lock待機中のprocessが同じ失敗回数を重複加算しないことを確認しています。

## Security / compatibility

- owner-scoped content確認がCache / stateより先
- SSRF target validation維持
- DNS pinning維持
- TLS peer / hostname verification維持
- manual redirect維持
- state metadataを公開APIへ出さない
- DB / Frontend / Stock変更なし
- PHP serialize / unserialize不使用
- runtime stateはGit ignore対象
- Item順序・件数を変更しない

## Build環境の制約

Build環境にはPDO SQLite、SimpleXML、mbstringがないため、次の6件はSKIPです。

1. PDO SQLite integration
2. SB-12 live Atom fixture
3. SB-14 live Parser matrix
4. M1-A live Normalized Parser
5. M1-C live Adapter matrix
6. M1-D live Identity Adapter matrix

M1-GのFake transport、filesystem、state、Retry、multi-process lock試験はこれらの拡張に依存せず、すべて実行しています。
