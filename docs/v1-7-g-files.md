# V1.7-G File boundary

V1.7-GでApplication Runtimeへ加えた変更はVersion Markerだけである。

```text
app/version.php
```

PrototypeとTestは`docs/prototypes/v1-7-g/`および`tests/test_v17g_*`へ分離した。

次は変更していない。

```text
database/
app/api.php
app/dashboard_widget.php
public/index.php
public/css/dashboard.css
public/js/dashboard.js
```

そのためV1.7-G単体では、既存Dashboardに縦2設定は表示されず、保存もされない。
