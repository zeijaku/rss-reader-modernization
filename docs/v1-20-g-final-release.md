# V1.20-G Final Release

V1.20-GはV1.20-F RC1を正式`1.20.0`へ昇格する最終工程です。RC1の機能コードは変更せず、Version、Asset revision、Release documentation、Package tooling、CI / Release Gateを正式Releaseへ切り替えます。

## Final markers

- `APP_VERSION=1.20.0`
- `APP_VERSION_LABEL=RSS Reader Modernization 1.20.0`
- `APP_ASSET_REVISION=1.20.0`
- intended tag: `v1.20.0`
- package status: `FINAL`
- publishable: `yes`

## Release boundary

- DB Migration / SQL: なし
- 新規必須Config / Secret: なし
- Public API endpoint: `public/api_v1.php`を維持
- V1.20-F RC1で確認済みの機能実装をそのまま正式化
- 既存Tagは上書きしない

## Verification

Current Full Regression、V1.17〜V1.19 Compatibility、V1.20-G Final Gate、PHP / JavaScript / Python syntax、secret scan、Apache syntax、Production / Complete package verifier、再展開後Gate、deterministic rebuildを実施します。
