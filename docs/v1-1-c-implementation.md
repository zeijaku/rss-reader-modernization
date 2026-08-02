# V1.1-C / R1 Implementation

## Purpose

Feed更新時に初めて検出した記事をNEWとして表示する。既存のItem Identity Resolverを再利用し、記事本文やTitleそのものはDBへ保存しない。

## Initial fetch

Feedごとの初回成功取得はBaselineとして扱う。初回に返った記事は`feed_item_state`へ登録するが、`seen_at`へ初回時刻を入れるためNEWにはしない。

2回目以降に初めて現れたItem Identityは`seen_at=NULL`で保存し、NEWとして表示する。

## NEW clear

Dashboardを表示・再読み込みしただけではNEWを解除しない。

- 記事行の`NEW`を押す: その記事だけ解除
- Feed見出しの`NEW n`を押す: そのFeedの未解除記事をまとめて解除

どちらも既存APIのAuthentication、CSRF、owner scopeを通る。Clientから送られたowner IDは使わない。

## Database

追加Tableは`feed_item_state`。実Table名は`DB_TABLE_PREFIX`を使う。

保存するのはowner、content、opaque Item Identity、初回/最終検出時刻、解除時刻、状態Flagだけで、Feed本文は保存しない。

同じUser・Feed・Item Identityの重複はUnique Indexで防ぐ。Foreign KeyはVersion 1.0.0の方針を維持して追加していない。

## Retention

有効なFeedの状態は、初回Baseline判定を壊さないため自動削除しない。削除済みFeedまたは存在しないFeedの状態だけを、初期90日後に整理する。

`APP_FEED_ITEM_STATE_RETENTION_DAYS`は任意設定で、`config/local.php`を変更しなくてもDefault 90日で動作する。

## Compatibility

`NormalizedItem::toArray()`の既存5項目は変更していない。Feed内部処理だけがIdentity付き配列を使用し、APIが安全確認済みの`item_identity`、`is_new`、`new_count`を追加して返す。

RSS 2.0、RSS 1.0、Atom、Cache hit、HTTP 304、stale-if-errorのどの経路でも同じIdentity処理を通す。
