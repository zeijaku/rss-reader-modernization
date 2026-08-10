# V1.6-D Lights Out状態保持・品質調整

## 目的

V1.6-Cで追加したLights Outへ、Browser Storageによる状態復元とKeyboard／Accessibility品質調整を追加します。既存Game Widget、Icon Quest、Clock Timer、Swipe処理は維持します。

## 保存する状態

- 現在盤面
- Reset用初期盤面
- Moves
- `playing`／`cleared`
- 保存日時
- Storage Schema／Game Version／盤面Size

Storage Keyは次の形式です。

`rssReader.miniGame.lightsOut.v1.user.{userId}.widget.{widgetId}`

User IDとWidget IDで分離するため、同一画面へ複数配置しても混線しません。

## Storage Fallback

1. localStorage
2. sessionStorage
3. memory

localStorageが利用不可、または書込み失敗した場合はsessionStorageへFallbackします。両方利用できない場合も、現在のPageを開いている間はmemoryで動作します。

## Recovery

localStorage、sessionStorage、memoryの保存Copyを個別に検証します。

- JSON破損
- 未知Schema／Game Version
- 25マス以外の盤面
- Boolean以外の盤面値
- 全消灯の初期問題
- Statusと盤面の矛盾
- 不正なMoves／savedAt

異常Copyだけを削除し、正常Copyがあれば保存日時が新しいものを採用します。正常Copyがない場合は解ける新規問題へ復旧します。

## Widget削除・種類変更

- Game Widget削除成功後、Icon QuestとLights Out双方の対象Widget Storageを削除
- `lights_out`から別Gameへ変更した場合、旧Lights Out Storageを削除
- `icon_quest`から別Gameへ変更した場合、旧Icon Quest Storageを削除

Server APIが成功した場合だけBrowser Storageを削除します。

## Keyboard／Accessibility

- 盤面はRoving tabindex方式
- Arrow Keyで上下左右へFocus移動
- Home／Endで現在行の先頭／末尾へ移動
- Enter／SpaceはNative Buttonの標準Activation
- 点灯状態は`aria-pressed`
- 行列と点灯／消灯状態を`aria-label`へ反映
- Clear後はFocusをResetへ移動
- Focus RingをLight／Dark Theme双方で明示
- `prefers-reduced-motion`ではTransitionを短縮

## Cache Busting

変更したAssetだけ個別に更新しました。

- `mini-game.css?v=1.6-d-r1`
- `mini-game.js?v=1.6-d-r1`
- `lights-out.js?v=1.6-d-r1`
- `dashboard.js?v=1.6-d-r1`

一元管理方式は導入していません。

## 非対象

- 複数Browser Tab間同期
- Server／DBへの盤面保存
- Solver、Hint、難易度、Best、統計
- 音、Vibration、Browser Notification
- DB Table／Column、Migration、SQL、Config、外部Library
