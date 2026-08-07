# V1.6-B File一覧

## Application

| File | 内容 |
|---|---|
| `app/version.php` | `1.6.0-dev.1`、V1.6-B / R1へ更新 |
| `public/js/dashboard.js` | Swipe Indicator、成立／取消表示、160msの成立確認時間 |
| `public/css/dashboard.css` | Smartphone限定Edge Indicator、Safe Area、Reduced Motion |
| `public/index.php` | 変更したDashboard CSS／JSの個別Cache Busting更新 |

## Test

新規V1.6-B Testと、過去機能を後続Versionで継続検証するためのVersion許可範囲更新を行いました。Game、Timer、Header等の期待動作は変更していません。

## 変更なし

- `app/api.php`
- `app/dashboard_widget.php`
- `app/mini_game.php`
- `public/js/mini-game.js`
- `public/js/clock-timer.js`
- `database/`
- `config/`
- `.htaccess`
