# V1.2-C / R4 Test Report

## 修正前に確認した原因

- Stock成功通知は`showNotice()`へ自動消去時間を渡していなかった。
- RSS概要処理は`[data-feed-content-id]`だけを参照し、Search Feedカードを取得できなかった。
- 空概要の`＋`は既存仕様どおりdisabledであり、変更不要と判断した。

## 重点テスト結果

- Application／Browser checks: PASS 268 / FAIL 0 / SKIP 0
- PHP syntax: PASS 99 files
- JavaScript syntax: PASS

## 主な確認内容

- Stock保存通知を表示し、2500msの自動消去を予約
- Search Feedの有効な`＋`から概要を展開
- Search Feed概要で`content`を表示
- 正常展開時にエラー通知を表示しない
- 空概要の`＋`は従来どおりdisabled
- 真の概要参照失敗時だけ制御されたエラーを表示
- 概要エラー通知を4000msで自動消去
- 通常Feedの概要、Stock、個別更新、記事表示を回帰確認
- Search Feedの検索、0件表示、見出し、1段Headerを回帰確認
- CSRF、API Action、Plain Text表示を維持

## 対象外

Memo更新時のセッション切れに対する`sessionStorage`下書き保護は中規模対応として今回実施していない。

## ZIP再展開確認

完成ZIPを別Directoryへ展開し、次を再確認した。

- ZIP SHA-256、CRC、重複Path、Path Traversal
- `SOURCE_MANIFEST.sha256`と全Payload 572件の一致
- `config/local.php`とRuntime Dataの非同梱
- JavaScript構文
- R4専用実Browser 18件
- 通常Feed実Browser回帰
- Search Feed見出し復元回帰
- Search Feed 1段Header回帰

再展開後もFAIL、SKIPは発生していない。
