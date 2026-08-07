# Version 1.6.0 確認Checklist

- [ ] Complete／Runtime ZIPのSHA-256がSidecarと一致する
- [ ] ZIPのCRC、重複Entry、危険Path、内部ManifestがPASSする
- [ ] `config/local.php`、実DB、Session、Log、Cache、Throttle Dataが含まれていない
- [ ] Footerが`RSS Reader Modernization 1.6.0`
- [ ] 左右Swipeで移動方向側のIndicatorが表示される
- [ ] 縦Scroll、Link、Button、Form、Timer、Game、DragでSwipeが誤動作しない
- [ ] Lights Outの角、辺、中央で正しいマスが反転する
- [ ] Moves、Reset、新しい問題、Clearが動く
- [ ] Reload後に盤面、Moves、Clear状態が復元される
- [ ] 複数Lights Out Widgetの状態が混線しない
- [ ] Arrow Key、Home、End、Enter、Spaceが動く
- [ ] Focus位置が見え、Clear後にResetへFocusが移る
- [ ] Smartphoneで横Overflowしない
- [ ] Light／Dark ThemeでON／OFFが判別できる
- [ ] Icon Quest、Clock Timer、通常RSS、Search Feed、Widget Dragが従来どおり動作する
- [ ] 音、Vibration、Browser通知が発生しない
- [ ] SQL、Migration、設定追加が不要である
- [ ] 本番確認完了前に`v1.6.0`Tagを作成していない
