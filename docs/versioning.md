V1.40-A開発Checkpoint: `1.40.0-dev.2`。正式Releaseは引き続き`1.39.2`です。V1.40.0の正式化は後続Phaseと本番確認後に行います。

# Versioning

現在の正式Release対象は `1.39.2` です。Active Release Candidateはありません。

```php
const APP_VERSION = '1.39.2';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.39.2';
const APP_ASSET_REVISION = '1.39.2';
```

`APP_ASSET_REVISION`はBrowserの長期Cacheを分離するCache keyです。正式ReleaseではApplication Versionと同じ値を使用します。

## Current version source

Application側の現在Versionは `app/version.php` を基準とします。

ただしRelease時は、`app/version.php`だけを読んでRelease Versionを自動決定しません。GitHub ActionsのRelease workflowへ `X.Y.Z` を明示入力し、その値を独立した期待値としてSource / package metadata / ZIP内Versionと照合します。

これにより、古いVersion値が複数のTestやPackage toolへhardcodeされたまま残る状態を避けます。

## Version変更時

正式Release準備では、同じ変更単位で次を揃えます。

- `APP_VERSION`
- `APP_VERSION_LABEL`
- `APP_ASSET_REVISION`
- README Stable release / Release tag
- CHANGELOG
- RELEASE_NOTES

Package builder / verifierのPython Sourceへ新Versionを埋め込む作業は行いません。

## Tag

正式Tagは `vX.Y.Z` です。

既存Tagは上書きしません。Release workflowはTagが存在する場合、そのTagが今回検証したCommitと同一かを確認します。別Commitの場合はReleaseを停止します。

V1.23-E以降の標準ReleaseではVersion固有のRelease branchを必須とせず、release-readyな `main` Commitから共通Release workflowを手動実行します。
