# Release package

正式Releaseでは、Production Runtime packageとComplete Source packageの2種類を生成します。

| Artifact | 用途 | Tests |
|---|---|---|
| `rss-reader-modernization-X.Y.Z.zip` | Server配置・Production Runtime | 含まない |
| `rss-reader-modernization-X.Y.Z-complete.zip` | Repository / Testsを含む完全Source | 含む |

各ZIPにはSHA-256 sidecarを付けます。`config/local.php`、実DB、生成済み`var/` Data、秘密情報、Legacy archiveは含めません。Production RuntimeのREADME / Documentationから辿る相対LinkはPackage verifyで解決可能か確認します。

## Versionの渡し方

Package toolへVersionをhardcodeしません。

Builder / Verifierへ、Release workflowまたは作業者から同じ `--release X.Y.Z` を明示的に渡します。

Builderはその入力値とSource内のVersionを照合し、Verifierは生成ZIP内のmetadata / `app/version.php` と入力値を独立して照合します。

## Production Runtime

```bash
VERSION='X.Y.Z'

python tools/build_release_package.py \
  --release "$VERSION" \
  --mode final \
  --output-dir dist

python tools/verify_release_package.py \
  --release "$VERSION" \
  "dist/rss-reader-modernization-${VERSION}.zip" \
  "dist/rss-reader-modernization-${VERSION}.zip.sha256"
```

正式Packageでは次が一致している必要があります。

- `APP_VERSION = X.Y.Z`
- `APP_VERSION_LABEL = RSS Reader Modernization X.Y.Z`
- `APP_ASSET_REVISION = X.Y.Z`
- `intended_release = X.Y.Z`
- `intended_tag = vX.Y.Z`
- `package_status = FINAL`
- `publishable = yes`

## Complete Source

```bash
VERSION='X.Y.Z'

python tools/build_complete_package.py \
  --release "$VERSION" \
  --output-dir dist

python tools/verify_complete_package.py \
  --release "$VERSION" \
  "dist/rss-reader-modernization-${VERSION}-complete.zip" \
  "dist/rss-reader-modernization-${VERSION}-complete.zip.sha256"
```

Complete Sourceには現在の `.github/workflows/ci.yml` と `.github/workflows/release.yml` を含めます。過去Version固有workflowはGit履歴 / Release tagで参照します。

## Preview / RC

Runtime builderのPreview / RC modeも `--release` で対象となる最終Versionを明示します。

- Preview: `X.Y.Z-dev.N`
- RC: `X.Y.Z-rcN`

正式Release workflowはFinal release専用であり、Preview / RCを公開するworkflowとしては使用しません。

## Artifact provenance

正式ReleaseではRuntime ZIPとComplete Source ZIPにGitHub Artifact Attestationを生成します。
SHA-256 sidecarはFile内容の整合性確認、AttestationはそのZIPがどのRepository / Workflow / Commitから生成されたかを検証するために使用します。どちらか一方で置き換えず、両方を確認します。

GitHub CLIが利用できる環境では、Release Assetを取得した後に次のように検証できます。

```bash
VERSION='X.Y.Z'

gh attestation verify \
  "rss-reader-modernization-${VERSION}.zip" \
  --repo zeijaku/rss-reader-modernization

gh attestation verify \
  "rss-reader-modernization-${VERSION}-complete.zip" \
  --repo zeijaku/rss-reader-modernization
```

Release workflow自身も、検証Jobから公開JobへArtifactを引き渡した後、SHA-256とAttestationの両方を再検証してからTag / GitHub Releaseを公開します。
