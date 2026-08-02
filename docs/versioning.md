# Visible Version Marker

Deployment確認のため、各配布Checkpointには画面上のVersion表示を付与する。

- 定義: `app/version.php`
- 未ログイン: Sign in / Registration画面下部
- ログイン後: メイン画面フッター
- CLI: `tools/healthcheck.php`
- Current development: `RSS Reader Modernization V1.1-D / R1`
- Stable release: `RSS Reader Modernization 1.0.0`
- Git Tag: `v1.0.0`（利用者がM4-G release commitへ作成）
- M2 completion checkpoint: `Frontend M2-G / R1`
- M1 completion checkpoint: `RSS Engine M1-G / R1`
- Security baseline ancestry: `Secure Baseline SB-15 / R3`

V1.1-Dは`APP_VERSION = 1.1.0-dev.3`の開発Checkpointで、Git Tagは作成しない。

正式Releaseは`APP_VERSION = 1.0.0`、`APP_VERSION_LABEL = RSS Reader Modernization 1.0.0`、Git Tag `v1.0.0`を同じRelease commitへ揃える。

回帰testはversion marker機構をrelease-genericに検証し、M4-G専用testでexact versionを検証する。次の修正版は同名TagやArtifactを上書きせず、`1.0.1`等の新しいVersionを使用する。
