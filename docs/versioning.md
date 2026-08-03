# Visible Version Marker

Application Versionと画面表示Labelは`app/version.php`で管理します。

```php
const APP_VERSION = '1.1.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.1.0';
```

- Stable release: `RSS Reader Modernization 1.1.0`
- Git Tag: `v1.1.0`
- Release commit: V1.1-Kの最終Commit

開発Checkpointでは`1.1.0-dev.N`と`V1.1-X / RN`を使用しました。正式Releaseでは開発中表記を残さず、Application Version、Label、Runtime ZIP、完全統合ZIP、Release Notes、Tagを同じ`1.1.0`へ揃えます。

過去のSB、M1、M2、M4、V1.1-B～J表記は履歴Document内に残しますが、現在Versionを示す入口には使用しません。
