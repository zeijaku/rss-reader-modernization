# Version 1.18.0 User Checklist

## 配置前

- [ ] 現在のCode、`config/local.php`、実DB、`var/`をBackup
- [ ] Runtime ZIPのSHA-256を確認
- [ ] ZIPは別Folderへ展開して内容確認
- [ ] DB Migration／SQLが不要であることを確認

## 配置

- [ ] `config/local.php`を上書きしない
- [ ] 実DB／`var/`生成Dataを上書きしない
- [ ] Runtime ZIPのApplication fileを配置
- [ ] SQL／Migrationは実行しない

## Browser

- [ ] Footerが`RSS Reader Modernization 1.18.0`
- [ ] Add Widget → Information → Connection Monitorが表示される
- [ ] Connection Monitorを追加出来る
- [ ] OnlineとLatencyが表示される
- [ ] 約5秒ごとにLatencyが更新される
- [ ] 30s／60s／5m Graphが動く
- [ ] Avg／Max／Jitterが表示される
- [ ] Excellent／Good／Fair／SlowがLatencyに応じて表示される
- [ ] DevTools Offline等で2回連続失敗後にOfflineになる
- [ ] Downtime／Last Disconnectが表示される
- [ ] 復旧時にRecovered後Onlineへ戻る
- [ ] Connection Monitorを複数置いてもProbeはPage全体で約5秒に1回
- [ ] Background tabでProbe停止、復帰時に即時再開
- [ ] Height 1／2がPCで崩れない
- [ ] Smartphone幅で横Scroll／情報欠落がない
- [ ] 利用ThemeでGraph／Badge／文字が読める
- [ ] Connection Monitor起因のGoogle／Cloudflare／Fast.com等への通信がない
- [ ] Speed Testの大容量通信がない
- [ ] JavaScript Console errorがない

## 問題時

- [ ] Connection Monitorだけの問題ならDevTools Networkで`connection_probe.php`のStatusを確認
- [ ] 重大な回帰があればVersion 1.17.2のBackupへCodeをRollback
- [ ] DB変更はないため、V1.18.0だけを理由にDB rollbackは不要
