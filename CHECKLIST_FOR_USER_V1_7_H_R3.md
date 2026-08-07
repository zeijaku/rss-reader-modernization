# V1.7-H / R3 確認項目

## DB

- [ ] V1.7-H/R2まで適用済みの現在環境ではSQLを再実行していない
- [ ] `widget_height`が既に存在している
- [ ] R3による新しいMigrationがないことを確認した

## 標準高さ

- [ ] Desktopの標準Widgetが220pxではなく320px以上になっている
- [ ] Tabletも標準Widgetが320px以上になっている
- [ ] Smartphoneは固定高にならず自動高である
- [ ] 縦2段Widgetが引き続き2 Rowを占有する

## RSS

- [ ] 自動＋高さ1で5件表示される
- [ ] 自動＋高さ2で10件表示される
- [ ] RSS追加／編集画面に表示件数がある
- [ ] 1～30件を指定して保存・再読込出来る
- [ ] 自動表示時に不要な縦横Scrollbarがない
- [ ] 手動指定が収まらない場合だけ縦Scroll出来る
- [ ] Search Feedの既存件数設定は変わっていない

## Clock／Game

- [ ] Clock高さ1で時計表示が切れない
- [ ] Clock高さ1でTimerのPreset／任意時間／Start／Pause／Resetを操作出来る
- [ ] Icon Quest高さ1で盤面と主要操作が切れない
- [ ] Lights Out高さ1で盤面とReset／新しい問題を操作出来る
- [ ] Clock／GameにR2由来の`overflow:hidden`による切れがない

## Regression

- [ ] Memo／Task／Calendarは必要時だけ縦Scroll出来る
- [ ] Drag & Drop／Keyboard並び替えが動く
- [ ] Smartphone Swipeが動く
- [ ] 30日ログイン、Logout、Password変更が動く
