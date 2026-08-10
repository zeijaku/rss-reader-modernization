# V1.7-H / R4 files

## Application

- `app/holiday.php` — 祝日Snapshot／Cache／CSV Parse／Safe Fetch／Atomic更新
- `app/data/japanese_holidays_snapshot.json` — 2026／2027 fallback Snapshot
- `app/calendar.php` — 月Dataへ祝日Map／更新要否追加
- `app/api.php` — Background refresh Action
- `app/http_fetch.php` — RSS defaultを維持したままRequest別Accept対応
- `app/common/common_conf.php` — Holiday runtime settings
- `app/bootstrap.php` — Holiday service load
- `app/version.php` — R4 marker
- `public/js/calendar.js` — 祝日Class／Tooltip／Background refresh
- `public/css/dashboard.css` — 祝日赤表示
- `config/local.php.example` / `config/.env.example` — URL／60日／Timeout設定例
- `.gitignore` — `var/cache/`直下Runtime Cache除外

## Database

変更なし。
