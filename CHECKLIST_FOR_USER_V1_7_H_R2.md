# V1.7-H / R2 確認項目

## DB

- [ ] V1.7-H / R1適用済み環境ではMigration 008を再実行していない
- [ ] `widget_height`が既に存在することを確認した
- [ ] 必要に応じてR2版Postflightで`widget_height`の定義と不正値0件を確認した

## Widget Grid／Scrollbar

- [ ] 通常のRSS Cardに横Scrollbarが表示されない
- [ ] 通常のRSS Cardに不要な縦Scrollbarが表示されない
- [ ] Clock／Gameに不要なScrollbarが表示されない
- [ ] Task／Memo／Calendarは内容が収まる場合にScrollbarが表示されない
- [ ] Task／Memo／Calendarは内容が多い場合だけ縦Scroll出来る
- [ ] Desktop 4列、Tablet 2列、Smartphone 1列が維持される
- [ ] 縦2段Widgetの高さとDrag & Dropが維持される

## RSS表示件数

- [ ] RSS追加画面に「表示件数」がある
- [ ] RSS編集画面に現在の表示件数が復元される
- [ ] 空欄で保存すると自動表示になる
- [ ] 1～30件を指定して保存出来る
- [ ] 0、31以上、不正値が拒否される
- [ ] 縦1＋自動でCard内に収まる件数になる
- [ ] 縦2＋自動で縦1より多くの記事を表示出来る
- [ ] 指定件数がCardへ収まらない場合だけ縦Scroll出来る
- [ ] 既存RSSは設定変更なしでも自動として表示される

## Regression

- [ ] Search Feedの表示件数設定が従来どおり動く
- [ ] 記事概要＋／－、記事Actions、新着Bell、個別更新が動く
- [ ] Clock Timer、Icon Quest、Lights Outが動く
- [ ] Smartphone Swipeが動く
- [ ] 30日ログイン、Logout、Password変更が動く
