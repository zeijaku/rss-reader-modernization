# Version 1.19 Release package

V1.19-Fでは正式`1.19.0` Runtime packageとComplete Source packageを生成します。

| Artifact | 用途 | Tests |
|---|---|---|
| `rss-reader-modernization-1.19.0.zip` | Server配置・正式Release用Runtime | 含まない |
| `rss-reader-modernization-1.19.0-complete.zip` | Repository / Testsを含む完全Source | 含む |

各ZIPにはSHA-256 sidecarを付けます。`config/local.php`、実DB、生成済み`var/`Data、秘密情報、Legacy archiveは含めません。

Runtime正式版は次で作成します。

```bash
python tools/build_release_package.py --mode final --output-dir dist
python tools/verify_release_package.py \
  dist/rss-reader-modernization-1.19.0.zip \
  dist/rss-reader-modernization-1.19.0.zip.sha256
```

Complete Sourceは次で作成します。

```bash
python tools/build_complete_package.py --output-dir dist
python tools/verify_complete_package.py \
  dist/rss-reader-modernization-1.19.0-complete.zip \
  dist/rss-reader-modernization-1.19.0-complete.zip.sha256
```

正式metadataは`intended_release=1.19.0`、`intended_tag=v1.19.0`、`package_status=FINAL`、`publishable=yes`です。Tag / GitHub Releaseの作成は、ユーザーから明示的にGitHub反映指示があった場合だけ行います。
