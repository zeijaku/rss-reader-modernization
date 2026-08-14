# Visible Version Marker

Application Versionと画面表示Labelは`app/version.php`で管理します。

```php
const APP_VERSION = '1.14.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.14.0';
```

- Stable release: `RSS Reader Modernization 1.14.0`
- Git Tag: `v1.14.0`
- Release commit: Version 1.14正式化の最終Commit

正式Releaseでは開発中表記を残さず、Application Version、Label、Runtime ZIP、完全Source ZIP、Release Notes、Tagを同じVersionへ揃えます。

過去のSB、M1、M2、M4、V1.x工程表記は履歴Document内に残しますが、現在Versionを示す入口には使用しません。
