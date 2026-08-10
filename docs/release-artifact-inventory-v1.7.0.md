# Version 1.7.0 Release Artifact Inventory

## Complete Source

- `rss-reader-modernization-1.7.0-complete.zip`
- `rss-reader-modernization-1.7.0-complete.zip.sha256`
- GitHub登録用
- Tests／`.github`／Migration／Documentationを含む
- `config/local.php`、実DB、Runtime Data、生成Cacheを含まない

## Runtime

- `rss-reader-modernization-1.7.0.zip`
- `rss-reader-modernization-1.7.0.zip.sha256`
- Server配置用
- Tests／`.github`を含まない
- Migration 007／008、Schema、運用Documentを含む

## Build characteristics

- Complete ZIP payload: 881 files（最終Documentation更新によるFile数は不変）
- Runtime ZIP payload: 437 files
- Fixed ZIP timestamp / sorted paths
- Internal SHA-256 manifest
- External SHA-256 sidecar
- CRC verification
- Duplicate / absolute / parent traversal path rejection
- Private config / runtime data / secret pattern rejection
- Same Sourceからの2回Buildでbyte-identicalになるDeterministic Build

最終SHA-256はZIP外の`.zip.sha256` Sidecarを正とします。Package内部Documentへ自身のZIP Hashを埋め込まず、Self-referentialなHash不整合を避けます。
