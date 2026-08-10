# V1.8-E Stock Actions / Domain / UI

## 目的

V1.8-B〜Dで追加した解除・検索・並び替え・Paginationを維持しながら、Stock一覧を「保存後に使う画面」として整理する。

## UI

Legacyのランダム色4列Cardを廃止し、1列Compact Listへ変更した。各行は以下を優先して表示する。

1. 記事タイトル
2. Domain
3. Stock保存日時
4. Article Actions

Domainは既存 `stock_data` のValidation後URLから `parse_url(..., PHP_URL_HOST)` で取得し、表示時のみ先頭 `www.` を省略する。URL自体はDB上で変更しない。

## Article Actions共通化

既存の `#articleActionsMenu` を通常RSS / Search Feed / Stockで共通利用する。

- Feed context: Stockへ保存 / URL Copy / X / Task
- Stock context: URL Copy / X / Task / Stock解除

Stock Itemのtriggerは `data-article-context="stock"` と `data-stock-id` を保持する。Menu位置計算は `.feed-card, .stock-card` の双方に対応する。

Stock解除は新規APIを作らず、V1.8-Bの `stock.delete` をそのまま使用する。

## Taskへ追加

Stock画面にはDashboard Task Widget DOMが無いため、`dashboard_widget_task_targets()` を追加した。

このhelperは `dashboard_widget` から、

- `widget_owner = authenticated user`
- `widget_type = 'task'`
- `widget_flag = 0`

だけを軽量取得する。Task本体は読み込まない。

- Task Widget 0件: Notice
- 1件: 直接 `task.item.create`
- 2件以上: 追加先選択Modal

実際のTask追加APIでもOwnership確認が行われるため、一覧取得とMutationの両方でOwnershipを維持する。

## DB

Table / Column / Index / Migration変更なし。
