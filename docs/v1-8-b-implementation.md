# Version 1.8-B Stock解除

## 方針

V1.8-Aで確認した既存`content_stock`構造を変更せず、`stock_flag`による論理削除を追加しました。

## Server side

`app/common/common_db.php`へ以下を追加しています。

- `find_owned_active_stock()`
- `delete_stock_owned()`

両方とも`stock_id`だけでは操作せず、認証Session由来の`stock_owner`と`stock_flag = 0`をSQL条件へ含めます。

`app/api.php`には`stock.delete`を追加しました。`stock_id`をPositive Integerとして検証し、他User／既解除／存在しないStockは404として扱います。

API Endpoint自体は変更せず、既存のSession認証、POST限定、CSRF検証を通過した後にDispatchされます。

## Frontend

既存Stock Cardへ「解除」Buttonを追加しました。今回はV1.8-EのStock一覧Design刷新を先取りせず、現行Cardを維持しています。

解除時は既存RSS削除と同様に確認を行い、成功時は対象`.stock-card`だけをDOMから除去します。最後の1件を解除した場合は`#stockEmptyState`を表示します。

## 既存RSS側との整合

現在の通常RSS側にはStock済み状態を永続表示する仕組みがないため、Stock解除後に通常RSS側のDOM状態を書き換える処理は不要です。Stock保存Action自体は変更していません。

## DB変更

なし。
