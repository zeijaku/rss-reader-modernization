# Versioning

現在の正式Releaseは以下です。

```php
const APP_VERSION = '1.19.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.19.0';
const APP_ASSET_REVISION = '1.19.0';
```

- Stable release: `RSS Reader Modernization 1.19.0`
- Stable tag: `v1.19.0`
- Previous release candidate: `RSS Reader Modernization 1.19.0-RC1`

`APP_ASSET_REVISION`はBrowserの長期Cacheを分離するCache keyです。V1.19.0正式版ではRC1と異なる`1.19.0`を使用し、RCの`immutable` Cacheを再利用しません。

Git tag / GitHub ReleaseはSourceとPackageが確定した後に作成します。Automationが勝手にTagを上書きする運用は行わず、既存`v1.19.0`が存在する場合は処理を中止して確認します。
