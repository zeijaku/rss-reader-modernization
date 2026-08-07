# Version 1.6.0 Release Artifact Inventory

| Artifact | Purpose | Status |
|---|---|---|
| `rss-reader-modernization-1.6.0-complete.zip` | Source、Tests、Documentation、GitHub metadata | 完了 |
| `rss-reader-modernization-1.6.0-complete.zip.sha256` | Complete ZIP SHA-256 | 完了 |
| `rss-reader-modernization-1.6.0.zip` | Server配置用Runtime | 完了 |
| `rss-reader-modernization-1.6.0.zip.sha256` | Runtime ZIP SHA-256 | 完了 |
| `SOURCE_MANIFEST.sha256` | Complete ZIP内部File Manifest | ZIP内部 |
| `RELEASE_MANIFEST.sha256` | Runtime ZIP内部File Manifest | ZIP内部 |
| `RELEASE_NOTES.md` | GitHub Release本文候補 | 完了 |

SHA-256の値は各`.zip.sha256` Sidecarを正とします。ZIP自身のHashを内部Documentへ埋め込まないため、Deterministic Buildを維持できます。

Private設定、実DB、Dump、Backup、Log、Session、Cache、Throttle Data、別Release ZIPは含めません。
