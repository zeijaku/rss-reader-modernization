# Version 1.5.0 Release Gate

## Release条件

- [x] `APP_VERSION`とLabelが`1.5.0`
- [x] V1.5-B Clock Timer基本機能回帰
- [x] V1.5-C Storage Recovery／複数Tab同期／復帰補正回帰
- [x] V1.5-C / R2～R4 Smartphone Feed／概要Icon／Cache切り分け回帰
- [x] V1.5-C / R5 Timer終了表示回帰
- [x] Secure Baseline、M1、M2、V1.1、V1.2、V1.3、V1.4を含むFull回帰
- [x] PHP／JavaScript／Python／Shell構文
- [x] Documentation Link
- [x] Secret Pattern／Runtime Data／Private設定確認
- [x] Complete ZIP／Runtime ZIP生成
- [x] SHA-256、CRC、重複Entry、危険Path、Manifest確認
- [x] 別Directoryへの再展開とV1.5 Focused回帰

## DB方針

Version 1.5では新しいTable、Column、Migration、SQL、必須設定を追加しません。Clock Widget登録は既存`dashboard_widget` Tableを利用し、Timer状態はBrowser Storageへ保存します。

## 手動確認として残る項目

- 実Hosting上の全Theme／実端末での目視
- 実MySQL接続と実Data保持
- 実Feed到達性
- 実Mail配送
- Backup／Restore drill
- Browser固有のPrivate Mode／Storage制限

自動TestとPackage検証がPASSしても、上記の環境固有項目は配置先で確認します。
