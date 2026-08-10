# Version 1.4.0 Release Gate

## Release条件

- [x] `APP_VERSION`とLabelが`1.4.0`
- [x] V1.4-B Game Widget基盤回帰
- [x] V1.4-C Icon Quest全4 Level回帰
- [x] V1.4-D Storage Recovery／Tutorial／Theme回帰
- [x] V1.4-D / R2 Game Header余白回帰
- [x] Secure Baseline、M1、M2、V1.1、V1.2、V1.3を含むFull回帰
- [x] PHP／JavaScript／Python／Shell構文
- [x] Documentation Link
- [x] Secret Pattern／Runtime Data／Private設定確認
- [x] Complete ZIP／Runtime ZIP生成
- [x] SHA-256、CRC、重複Entry、危険Path、Manifest確認
- [x] 別Directoryへの再展開とFocused回帰

## DB方針

Version 1.4では新しいTable、Column、Migration、SQL、必須設定を追加しません。Game Widgetの登録は既存`dashboard_widget` Tableを利用し、盤面、Best手数、勝敗数、Tutorial状態はBrowser Storageへ保存します。

## 手動確認として残る項目

- 実Hosting上の全Theme／実端末での目視
- 実MySQL接続と実Data保持
- 実Feed到達性
- 実Mail配送
- Backup／Restore drill
- Browser固有のPrivate Mode／Storage制限

自動TestとPackage検証がPASSしても、上記の環境固有項目は配置先で確認します。
