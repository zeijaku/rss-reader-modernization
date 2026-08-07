# Version 1.7.0 Release package

## Package種類

| Artifact | 用途 | Tests / .github |
|---|---|---|
| `rss-reader-modernization-1.7.0-complete.zip` | GitHubへ登録する完全Source | 含む |
| `rss-reader-modernization-1.7.0.zip` | Server配置用Runtime成果物 | 含まない |

両方とも固定Timestamp・Path順で生成し、同一Sourceから同じSHA-256になるDeterministic Buildとします。

## 生成

PowerShellからRepository rootで実行します。

```powershell
python .\tools\build_complete_package.py --output-dir ..\release-output
python .\tools\build_release_package.py --mode final --output-dir ..\release-output
```

## 検証

```powershell
python .\tools\verify_complete_package.py `
  ..\release-output\rss-reader-modernization-1.7.0-complete.zip `
  ..\release-output\rss-reader-modernization-1.7.0-complete.zip.sha256

python .\tools\verify_release_package.py `
  ..\release-output\rss-reader-modernization-1.7.0.zip `
  ..\release-output\rss-reader-modernization-1.7.0.zip.sha256
```

## Complete ZIPへ含める

Repositoryへ登録するSource、tests、`.github`、Documentation、Migration／Audit SQL、Package Toolを含みます。`config/local.php`、実DB、Session／Log／Cache、Release ZIPそのものは含めません。

## Runtime ZIPへ含める

Application、Public Asset、設定Example、Schema／Migration／Audit SQL、運用Tool、License、Release Notes、設置・更新・復旧Document、空のRuntime Directoryを含めます。

## Runtime ZIPへ含めない

- `tests/`、`.github/`、Git作業用Checklist
- `config/local.php`、`.env`、秘密鍵、Token
- 実DB、Dump、Backup、Log、Session、Throttle Data
- Feed Cache、Japanese Holiday Cacheを含む`var/cache/`生成Data
- Legacy ZIP、別Release ZIP、Python Cache

## Build metadata

Runtime ZIP内の`RELEASE_BUILD.txt`は次を記録します。

```text
package_status=FINAL
application_version=1.7.0
application_label=RSS Reader Modernization 1.7.0
intended_release=1.7.0
intended_tag=v1.7.0
publishable=yes
```

Complete ZIPは`SOURCE_BUILD.txt`と`SOURCE_MANIFEST.sha256`、Runtime ZIPは`RELEASE_BUILD.txt`と`RELEASE_MANIFEST.sha256`を持ちます。

## 安全境界

Builderはunsafe path、Symlink、Private設定、実DB系拡張子、別ZIP、Python Cache、生成済みRuntime Dataを拒否します。VerifierはSHA-256、CRC、重複Path、Absolute／Parent Traversal、Manifest、Version、Secret Patternを確認します。

`final` modeは`APP_VERSION = '1.7.0'`と`APP_VERSION_LABEL = 'RSS Reader Modernization 1.7.0'`が完全一致しない限り実行できません。
