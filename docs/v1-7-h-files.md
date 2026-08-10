# V1.7-H File boundary

## Runtime change

```text
app/version.php
app/api.php
app/dashboard_widget.php
app/search_feed.php
app/mini_game.php
public/index.php
public/css/dashboard.css
public/js/dashboard.js
public/js/calendar.js
database/schema.sql
database/migrations/008_v1_7_widget_height.sql
database/audit/v1_7_h_preflight.sql
database/audit/v1_7_h_postflight.sql
```

## Unchanged boundary

```text
Remember Token Table／Migration 007
30日ログインCookie／Session仕様
RSS Parser／Fetch／Cache Engine
Stock／記事Actions
Clock Timer Storage
Icon Quest／Lights Out Storage
public/.htaccess Security Header
config/local.php項目
外部Library
```

V1.7-Hは既存API Dispatcherへ入力項目を追加するが、新しいAPI Routeは追加しない。
