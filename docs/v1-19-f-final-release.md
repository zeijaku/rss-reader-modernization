# V1.19-F Final Release

## Scope

V1.19-FはV1.19.0-RC1で本番互換確認まで完了したSourceを、正式`1.19.0`へ確定するPhaseです。新機能追加は行いません。

## Final version

- APP_VERSION: `1.19.0`
- APP_VERSION_LABEL: `RSS Reader Modernization 1.19.0`
- APP_ASSET_REVISION: `1.19.0`
- intended tag: `v1.19.0`
- package status: `FINAL`
- publishable: `yes`

正式Asset RevisionをRC1と分けることで、RC1の`immutable` Cacheを再利用しません。

## Finalization work

- README / CHANGELOG / RELEASE_NOTES / versioning / update / package docsを正式1.19.0へ整合。
- Runtime / Complete package builder・verifierを正式1.19.0へ確定。
- V1.19-F final release focused gateとGitHub Actions Release Gateを追加。
- V1.18.0公開時の一時Release Branchだけに存在した`.github/.v118-publish-*` Marker 6個をComplete Sourceから除外。正式`v1.18.0` Tagには存在しないため、Application Runtimeの変更ではなくSource packageの整理として扱う。

## Compatibility

- DB Migration: なし
- SQL: 不要
- 新規必須Config / Secret: なし
- Public API Endpoint / API Action: 維持
- V1.19.0-RC1からのApplication behavior追加変更: なし

## GitHub policy

Commit / Push / Tag / GitHub Releaseは、このPhaseのSource確定だけでは実行しません。ユーザーから明示的にGitHub反映指示があった場合だけ実行します。
