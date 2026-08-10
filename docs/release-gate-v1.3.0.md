# Version 1.3.0 Release Gate

## Release条件

- [x] `APP_VERSION`とLabelが`1.3.0`
- [x] V1.3-B Drawer回帰
- [x] V1.3-C Header回帰
- [x] V1.3-D共通余白回帰
- [x] Secure Baseline、M1、M2、V1.1、V1.2を含むFull回帰
- [x] PHP／JavaScript／Python／Shell構文
- [x] Documentation Link
- [x] Secret Pattern／Runtime Data／Private設定確認
- [x] Complete ZIP／Runtime ZIP生成
- [x] SHA-256、CRC、重複Entry、危険Path、Manifest確認
- [x] 別Directoryへの再展開とFocused回帰

## DB方針

Version 1.3ではTable、Column、Migration、SQL、API、必須設定を追加しません。

## 手動確認として残る項目

- 実Hosting上のHeader／Drawer／全Theme目視
- 実MySQL接続と実Data保持
- 実Feed到達性
- 実Mail配送
- Backup／Restore drill

自動TestとPackage検証がPASSしても、上記の環境固有項目は配置先で確認します。
