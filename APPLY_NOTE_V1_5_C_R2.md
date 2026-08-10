# V1.5-C / R2 適用メモ

V1.5-C / R1を基準に、スマートフォンでFeedが横へ広がる問題だけを修正したCheckpointです。

## 配置対象

- `app/version.php`
- `public/index.php`
- `public/css/dashboard.css`

SQL、Migration、DB構造、`config/local.php`の変更はありません。

配置後はSafariを再読み込みしてください。`dashboard.css?v=1.5-c-r2`へ変更しているため、旧CSS Cacheを避けて読み込みます。
