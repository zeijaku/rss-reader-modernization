# V1.4-B Mini Game Widget基盤

## 目的

Version 1.4で小型Game Widgetを追加するための共通基盤を実装する。V1.4-BではGame本体を完成させず、既存Dashboardへ安全に追加・配置・削除できることと、Browser StorageをWidget単位で分離できることを確認する。

## Server側

`game`を既存Dashboard Widget Typeへ追加した。Game Widgetは`widget_reference_id=NULL`とし、新しい参照Tableを作らない。

`widget_config`には次だけを保存する。

```json
{
  "schema": 1,
  "title": "Icon Quest",
  "game": "icon_quest"
}
```

Create／Update／Deleteは認証済みUser IDをOwnerとして利用し、ClientからOwner IDを受け取らない。更新と削除は既存のOwner Scope LockとTransactionを利用する。

## Browser Storage

Storage Keyは次の形式とした。

```text
rssReader.miniGame.iconQuest.v1.user.{userId}.widget.{widgetId}
```

Storageの優先順位は次の通り。

1. `localStorage`
2. `sessionStorage`
3. JavaScript Memory

Storage値は信頼せず、Root Object、Schema、Game名、Status、Level ID、Moves、保存時刻を検証する。JSON Parse失敗や未知Schemaは初期状態へ戻し、Storage値を`innerHTML`へ渡さない。

## Widget削除

Server側の論理削除APIが成功した後だけ、同じWidget IDのBrowser Storageを削除する。API失敗時はStorageを維持する。

## Mock盤面

5×5固定盤面をHTML ButtonとFont Awesomeで表示する。

- Player: `fa-user-shield`
- Enemy: `fa-skull-crossbones`
- Treasure: `fa-gem`
- Goal: `fa-door-open`
- Wall: `fa-cube`

盤面は`role=grid`、各Cellは`role=gridcell`と行列Labelを持つ。V1.4-Bでは操作不能であることを`aria-disabled`とScreen Reader向け説明で示す。

## 操作競合

Game Widget全体へ`data-dashboard-swipe-ignore=true`を設定し、SmartphoneのDashboard Tab Swipeと盤面Touchが競合しないようにした。Widget並べ替えは引き続き専用Drag Handleからだけ開始する。

## V1.4-Cへ残した内容

- Player移動
- Arrow Key／WASD
- 隣接Cell Tap
- Enemy AI
- Treasure取得
- Goal／Clear／Game Over
- Moves／Best Score
- 実際の途中盤面保存と復元
