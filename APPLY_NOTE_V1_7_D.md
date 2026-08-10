# V1.7-D / R1 適用メモ

## 目的

V1.7-CでVersion付きに統一したStatic AssetへHTTP Cache Policyを設定し、動的HTML／API／Error Responseは明示的な`no-store`へ整理します。同時に、影響範囲を限定したSecurity Headerを`public/.htaccess`へ追加します。

## Application

- Version: `1.7.0-dev.3`
- Label: `RSS Reader Modernization V1.7-D / R1`
- DB／Migration／SQL: なし
- API Route／設定／外部Library: なし
- HSTS: 未追加
- 全面CSP: 未追加

## 配置対象

- `app/version.php`
- `app/response_cache.php`
- `app/bootstrap.php`
- `app/error_response.php`
- `app/session.php`
- `public/index.php`
- `public/api_v1.php`
- `public/logout.php`
- `public/.htaccess`

Server固有の`.htaccess`を使用している場合は、既存内容をBackupした上で`public/.htaccess`のHeader部分を比較して反映してください。
