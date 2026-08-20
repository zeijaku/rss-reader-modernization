# V1.18.0 final pre-Git production checklist

## 配置前
- [ ] 現在のCode、`config/local.php`、実DB、`var/`をBackupする。
- [ ] Runtime ZIPのSHA-256を確認する。
- [ ] SQL / Migrationは実行しない。

## 配置
- [ ] Runtime ZIPを別Folderへ展開し、Codeだけを相対Pathで更新する。
- [ ] `config/local.php`、実DB、`var/`生成Dataを上書きしない。
- [ ] Footerが`RSS Reader Modernization 1.18.0`であることを確認する。
- [ ] Asset URLが`?v=1.18.0-r2`になっていることを確認する。通常Reloadで旧1.18.0候補Cacheを回避出来る。

## Connection Monitor
- [ ] Add Widget → Information → Connection Monitorが表示される。
- [ ] Online / Latency / 30s・60s・5m / Avg / Max / Jitter / Qualityが動作する。
- [ ] `connection_probe.php`は約5秒に1回、HTTP 204。
- [ ] 2回連続到達不能でOffline、復旧後Recovered→Online。
- [ ] 複数MonitorでもPage全体のProbeは約5秒に1回。
- [ ] Background tabでは停止し、復帰時に即時Probe。

## Git前R2修正
- [ ] 長時間放置後、Remember Meが有効なら更新操作で`CSRF validation failed.`にならない。
- [ ] Remember Me無効で認証が失効した場合はLogin画面へ戻る。
- [ ] Smartphone CalendarでPage右側に余白が発生しない。
- [ ] Calendarの日〜土7列がCard幅内に収まる。
- [ ] Page全体が横方向へずれない。

## 最終確認
- [ ] Browser Consoleに新規Errorがない。
- [ ] Server error logに新規Errorがない。
- [ ] DB schema / local.php変更が不要であることを再確認する。
