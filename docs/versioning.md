# Visible Version Marker

Application Versionと画面表示Labelは`app/version.php`で管理します。

```php
const APP_VERSION = '1.2.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.2.0';
```

- Stable release: `RSS Reader Modernization 1.2.0`
- Git Tag: `v1.2.0`
- Release commit: Version 1.2正式化の最終Commit

Version 1.2開発中は`1.2.0-dev.N`を使用しました。正式Releaseでは開発中表記を残さず、Application Version、Label、Runtime ZIP、完全統合ZIP、Release Notes、Tagを同じ`1.2.0`へ揃えます。

過去のSB、M1、M2、M4、V1.1、V1.2-A～D表記は履歴Document内に残しますが、現在Versionを示す入口には使用しません。
