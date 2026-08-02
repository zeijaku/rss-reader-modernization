# V1.1-D / R1 Implementation

## Purpose

既存Feed、将来追加するClock、Memo、Task、Calendarを同じDashboardへ配置するため、共通のWidget配置基盤を追加する。

この工程ではDrag & Dropそのものは実装しない。V1.1-Eで並び替えを追加出来るよう、Widget ID、4タブ上の位置、順番、幅、Style、設定の保存先までを用意する。

## Source of truth

Feed本体は従来どおり`content`Tableを正本とする。`dashboard_widget`は表示位置だけを持ち、Feed URLやFeed取得状態を重複保存しない。

```text
content             Feed URLと既存Feed情報
dashboard_widget    Dashboard上の配置情報
```

この分離により、Application CodeをV1.1-Cへ戻しても既存Feedを失わない。

## Existing Feed migration

Migration時に、有効な既存Feedへ1件ずつ`feed`Widgetを作成する。

- owner: `content_owner`
- tab: `content_location`
- reference: `content_id`
- initial sort order: `content_id`
- width: `1`（従来のDesktop 4列幅）
- style: `content_style`

再実行時は同じFeed Widgetを重複作成しない。location、style、論理削除状態は既存Feedへ合わせるが、V1.1-E以降に変更した`widget_sort_order`はリセットしない。

## Feed CRUD synchronization

Feed追加、Style変更、論理削除は`content`と`dashboard_widget`を同じTransactionで更新する。

- Feed追加: Content作成後にFeed Widgetを作成
- Feed変更: owned ContentをLockし、ContentとWidget Styleを同期
- Feed削除: Contentと対応Widgetを同時に論理削除
- 途中失敗: TransactionをRollback

Clientからowner IDを受け取らず、Login済みUser IDを使用する。別UserのContentやWidgetは更新出来ない。

## Widget model

初期の`widget_type`は次のAllowlistとする。

```text
feed
clock
memo
task
calendar
```

`widget_config`はMySQL 5.6 / MariaDB互換性を保つためJSON型ではなくTEXTとし、Application側でJSON Object、深さ、長さを検証する。

Widget幅は1〜4の小さい整数で管理する。V1.1-DのFeed Widgetはwidth 1で従来のMobile 1列、Tablet 2列、Desktop 4列を維持する。

## Dashboard rendering

4タブ構成は変更しない。DashboardはLogin User、現在のtab、active flagでWidgetを取得し、`widget_sort_order`、`widget_id`の順に表示する。

Feed CardにはV1.1-EのDrag & Dropで使うData Attributeを追加した。

```text
data-dashboard-widget-id
data-dashboard-widget-type
data-dashboard-widget-location
data-dashboard-widget-sort-order
```

Drag Handle、並び替えEvent、並び替えAPIはV1.1-Eの範囲とし、この工程へ混在させていない。

## API

読み取り用に`widget.list`を追加した。Authentication、CSRF、owner scopeは既存API共通Gateを使用する。

返却値に`widget_owner`は含めず、Login Userの指定tabに属する公開可能な配置情報だけを返す。

Migration前のCode先行配置を検出した場合は、Widget系Actionが`dashboard_widget_unavailable` / HTTP 503を返す。更新手順ではCodeを本番利用する前にMigrationを完了する。

## Compatibility

- Registration、Login、Session、4タブを維持。
- Feed CRUD、Stock、Settingsを維持。
- V1.1-B Tracking Parameter除去を維持。
- V1.1-C NEW表示と`feed_item_state`を維持。
- RSS 2.0、RSS 1.0、Atom、Cache、HTTP 304、Retry、stale-if-errorを変更しない。
- 8テーマ、Responsive、Keyboard、Focus、ARIAの既存構造を維持。
- Foreign Keyは既存DB互換性とRollbackを優先して追加しない。
