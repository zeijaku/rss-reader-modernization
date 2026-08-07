# V1.7-H / R2 File boundary

## Runtime change from V1.7-H / R1

```text
app/version.php
app/api.php
app/dashboard_widget.php
public/index.php
public/css/dashboard.css
public/js/dashboard.js
```

## SQL compatibility revision

```text
database/migrations/008_v1_7_widget_height.sql
database/audit/v1_7_h_preflight.sql
database/audit/v1_7_h_postflight.sql
```

RSS表示件数機能は既存`widget_config`を利用するため、新しいMigration／Table／Columnはない。

## Unchanged boundary

```text
database/schema.sql のV1.7-H widget_height定義
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
