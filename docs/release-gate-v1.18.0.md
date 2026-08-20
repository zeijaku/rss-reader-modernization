# Version 1.18.0 Release Gate

## Automated gate

- `bash tests/run-current.sh`
- `bash tests/run-v117.sh`
- `bash tests/run-v1171.sh`
- `bash tests/run-v1172.sh`
- `bash tests/run-v118.sh`
- PHP syntax for repository PHP files
- JavaScript syntax for Connection Monitor
- high-signal secret scan
- deterministic Runtime package build + verify
- deterministic Complete source package build + verify

## Production gate

- Footer version is `RSS Reader Modernization 1.18.0`.
- Connection Monitor appears under Add Widget → Information.
- Online／Latency／History／Avg／Max／Jitter／Quality are displayed.
- Offline is confirmed after consecutive unreachable probes and recovery returns to Online.
- Downtime／Last Disconnect are reasonable.
- Multiple Connection Monitor widgets still share approximately one probe per 5 seconds.
- Hidden tab pauses polling and visible return resumes immediately.
- PC Height 1／2 and smartphone layouts do not overflow.
- No Connection Monitor traffic to external monitoring or speed-test services.
- No SQL／Migration／new secret is required.

## Release artifacts

- `rss-reader-modernization-1.18.0.zip`
- `rss-reader-modernization-1.18.0.zip.sha256`
- `rss-reader-modernization-1.18.0-complete.zip`
- `rss-reader-modernization-1.18.0-complete.zip.sha256`
