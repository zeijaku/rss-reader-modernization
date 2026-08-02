# Version 1.0.0 Release package

## 目的

Repository用Checkpoint ZIPと、利用者へ配布するRuntime Release ZIPを分けます。Checkpoint ZIPはtest、Implementation記録、作業Checklistを含みます。Runtime Release ZIPはApplication、設定example、DB資料、運用資料、License、Release Notesへ絞ります。

## Package mode

`tools/build_release_package.py` は3つのmodeを持ちます。

| mode | 必要なAPP_VERSION | 用途 | 公開可否 |
|---|---|---|---|
| `preview` | `M4-E R1` | M4-EでPackage構成を確認 | 不可 |
| `rc` | `1.0.0-rcN` | M4-FのRelease Candidate | Pre-releaseとしてのみ |
| `final` | `1.0.0` | M4-Gの正式成果物 | 最終Gate後のみ |

M4-Eでは次を生成します。

```powershell
python tools/build_release_package.py --mode preview --output-dir ..\release-output
```

出力:

```text
rss-reader-modernization-1.0.0-preview-m4-e.zip
rss-reader-modernization-1.0.0-preview-m4-e.zip.sha256
```

Preview ZIPの `RELEASE_BUILD.txt` は次を明示します。

```text
package_status=PREVIEW
publishable=no
intended_release=1.0.0
intended_tag=v1.0.0
```

## Release ZIPへ含める

- `.htaccess`
- Application: `app/`、`public/`
- 設定example: `config/`
- sanitized DB資料: `database/`
- License: `LICENSE`、`THIRD_PARTY_NOTICES.md`、`licenses/`
- `README.md`、`CHANGELOG.md`、`RELEASE_NOTES.md`、`SECURITY.md`
- Runtime / migration tools: `tools/`
- 空Runtime directory: `var/`の`.gitkeep`
- 公開Documentation一式（過去工程のImplementation / Test記録を含む）
- `RELEASE_BUILD.txt`
- `RELEASE_MANIFEST.sha256`

## Release ZIPへ含めない

- `.git/`、`.github/`、`.gitignore`
- `tests/`
- `CHECKLIST_FOR_USER.md`
- 過去Checkpoint package manifest (`docs/package-manifest*.txt`)
- `config/local.php`、real `.env`
- 実DB、DB dump、Backup、Log、Session、Cache、Lock、State
- `rss.zip`、`rss.sql`
- 入れ子ZIP
- `__pycache__`、`.pyc`

## Deterministic build

Builderはfile順、ZIP timestamp、permissionを固定します。同じSourceから同じmodeで2回BuildしたZIPは同じSHA-256になることをM4-E testで確認します。

生成日時やLocal pathをZIP内部へ書かず、Build内容は `RELEASE_BUILD.txt` の固定項目で識別します。

## 内部Manifestと外部SHA-256

`RELEASE_MANIFEST.sha256` はZIP内部fileのSHA-256です。Manifest自身は自己参照できないため対象外です。

外部 `.zip.sha256` はZIP全体を検証します。

```powershell
Get-FileHash .\rss-reader-modernization-1.0.0-preview-m4-e.zip -Algorithm SHA256
Get-Content .\rss-reader-modernization-1.0.0-preview-m4-e.zip.sha256
```

Python verifier:

```powershell
python tools/verify_release_package.py `
  ..\release-output\rss-reader-modernization-1.0.0-preview-m4-e.zip `
  ..\release-output\rss-reader-modernization-1.0.0-preview-m4-e.zip.sha256
```

## 安全境界

BuilderはallowlistからPackageを作り、unsafe path、symlink、Private設定、実DB系拡張子、入れ子ZIP、Runtime生成fileを検出した場合は停止します。

`final` modeは `APP_VERSION = '1.0.0'` と `APP_VERSION_LABEL = 'RSS Reader Modernization 1.0.0'` が完全一致しない限り実行できません。M4-Eで誤って正式Artifactを作らないための境界です。
