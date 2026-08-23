# Versioning

現在の正式Releaseは`1.20.0`です。Active Release Candidateはありません。

```php
const APP_VERSION = '1.20.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.20.0';
const APP_ASSET_REVISION = '1.20.0';
```

- Stable release: `RSS Reader Modernization 1.20.0`
- Stable tag: `v1.20.0`
- Previous stable release: `RSS Reader Modernization 1.19.0`
- Previous stable tag: `v1.19.0`

`APP_ASSET_REVISION`はBrowserの長期Cacheを分離するCache keyです。正式V1.20.0では`1.20.0`を使用し、V1.19.0、V1.20途中checkpoint、V1.20.0-RC1の`immutable` Cacheを再利用しません。

既存Tagは上書きしません。Release作成前にRemoteの`v1.20.0`不在と、Tag対象Commitが正式SourceのCommitであることを確認します。
