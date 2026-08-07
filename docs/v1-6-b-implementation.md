# V1.6-B Smartphone Tab Swipe表示改善

## 目的

スマートフォンでDashboard TabをSwipeした際、切替方向と成立状態を画面端の小さな矢印で示します。既存のSwipe判定、操作除外、Tab URL契約は維持します。

## 方向

| Gesture | 移動 | 表示 |
|---|---|---|
| 左へSwipe | 次のTab | 右端に左向き矢印`‹` |
| 右へSwipe | 前のTab | 左端に右向き矢印`›` |

最初と最後のTabでは循環せず、移動先が存在しない方向のIndicatorは表示しません。

## 既存判定の維持

次の値と条件は変更していません。

- Smartphone: `max-width: 767.98px`
- 切替距離: 64px
- 画面端除外: 左右24px
- 横Intent開始: 横14px超、横移動が縦移動の1.25倍超
- 縦Scroll取消: 縦18px超かつ縦移動優勢
- 最終判定: 横移動が縦移動の1.3倍以上
- 最大時間: 1,200ms
- Multi-touch無効

Link、Button、Input、Textarea、Select、Label、Contenteditable、Modal、Drawer、Widget Drag Handle、Calendar、横Scroll領域、`data-dashboard-swipe-ignore=true`は引き続き対象外です。

## Indicator

Indicatorは明確な横Intentが成立した時点で遅延生成します。

- `aria-hidden=true`
- `pointer-events: none`
- Fixed表示
- iPhone Safe Areaを考慮
- 移動量を64px閾値に対する割合へ変換し、OpacityとEdge方向の位置へ反映
- 成立時は強調状態へ切替
- 不成立時は220msで静かに消去
- 成立時は280ms以内で消去

Swipe成立時はIndicatorを描画するため、従来の`./?tab=N`移動を160msだけ遅らせます。Indicatorを生成出来ない環境では従来どおり即時移動します。

## Reduced Motion

`prefers-reduced-motion: reduce`では横方向のTransformを0へ固定し、既存の全体RuleによってTransition時間を最小化します。方向情報そのものは残します。

## Cache

変更した`dashboard.css`と`dashboard.js`だけQueryをV1.6-B / R1へ更新しました。一元的なCache Busting管理は追加していません。

## Security／Data

- DB、Migration、SQL変更なし
- API Route、Request、Response変更なし
- Config追加なし
- Browser Storage追加なし
- 外部Library追加なし
- HTML挿入APIを使用せず、IndicatorはDOM APIと`textContent`で生成
