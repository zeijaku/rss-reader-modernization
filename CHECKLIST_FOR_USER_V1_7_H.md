# V1.7-H / R1 確認項目

- [ ] Preflightで対象`dashboard_widget` Tableと現在のColumnを確認した
- [ ] Migration 008がErrorなく完了した
- [ ] Postflightで`widget_height`が`TINYINT UNSIGNED NOT NULL DEFAULT 1`になっている
- [ ] 既存Widgetの`widget_height`がすべて1になっている
- [ ] RSS／Search Feed／Clock／Memo／Task／Calendar／Gameの追加画面に「縦幅」がある
- [ ] 各Widget編集画面で「標準」「縦2段」を選べる
- [ ] 縦2段で保存後、再読み込みしても設定が維持される
- [ ] Desktopで縦2Widgetが上下2段を占有する
- [ ] Tabletで2列表示になり、横幅3／4は2列内へ収まる
- [ ] Smartphoneで1列となり、縦2設定でも不要な固定高が残らない
- [ ] 長いFeed／Task／MemoはWidget本文内でScroll出来る
- [ ] Drag & Drop後も縦幅と並び順が維持される
- [ ] Arrow／Home／Endによる並び替えが従来どおり動く
- [ ] Clock Timer、Icon Quest、Lights Outを操作出来る
- [ ] 全8 Themeで文字、境界、Scroll領域を確認した
- [ ] 30日ログイン、Logout、Password変更が従来どおり動く
