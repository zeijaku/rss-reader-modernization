# V1.6-D 配置対象

## Application変更ファイル

```text
app/version.php
public/index.php
public/js/dashboard.js
public/js/lights-out.js
public/css/mini-game.css
```

`public/js/mini-game.js`はApplication処理の変更はありませんが、読込みURLの個別Cache Bustingを`public/index.php`で更新しています。

## 配置不要

- `database/`
- `config/`
- `.htaccess`
- `app/api.php`
- `public/js/clock-timer.js`

SQL、Migration、Config追加、外部Library追加はありません。
