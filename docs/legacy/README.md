# Legacy evidence policy

The original `rss.zip`, supplemental `api_v1.php`, production-derived `rss.sql`, logs, session files and production secrets are evidence only. They are intentionally not copied into the public repository tree.

- Hashes: `source-sha256.txt`
- Original file/size inventory: `legacy-tree-manifest.txt`
- `api_v1.php` is treated as a supplemental Legacy source missing from `rss.zip`.
- `rss.sql` may contain production-derived data and must never be committed.
