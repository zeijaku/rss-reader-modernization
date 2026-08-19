# Version 1.17.2 Release package

## Package種類

| Artifact | 用途 | Tests / .github |
|---|---|---|
| `rss-reader-modernization-1.17.2-complete.zip` | GitHub作業Folder相当の完全Source成果物 | 含む |
| `rss-reader-modernization-1.17.2.zip` | Server配置用Runtime成果物 | 含まない |

両方とも固定Timestamp・Path順で生成し、同一Sourceから同じSHA-256になるDeterministic Buildとします。

Version 1.17.2では、本番で必要な変更を適用Script内だけに持たせません。Runtime ZIPへ収録されるApplication fileは、そのまま配置出来る更新済み実ファイルです。

## 生成

```bash
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
```

## 検証

```bash
python tools/verify_complete_package.py \
  ../release-output/rss-reader-modernization-1.17.2-complete.zip \
  ../release-output/rss-reader-modernization-1.17.2-complete.zip.sha256

python tools/verify_release_package.py \
  ../release-output/rss-reader-modernization-1.17.2.zip \
  ../release-output/rss-reader-modernization-1.17.2.zip.sha256
```

## Runtime ZIPへ含める

Application、Public Asset、設定Example、Schema / Migration / Audit SQL、運用Tool、License、Release Notes、設置・更新・復旧Document、空のRuntime Directoryを含めます。

## Runtime ZIPへ含めない

- `tests/`、`.github/`、Git作業用Checklist
- `config/local.php`、`.env`、Bearer Tokenを含む秘密鍵／Token
- 実DB、Dump、Backup、Log、Session、Cache、Throttle Data
- Legacy ZIP、別Release ZIP、Python Cache

X Timelineの`APP_X_BEARER_TOKEN`はRuntime ZIPへ入れず、配置先のServer固有`config/local.php`またはEnvironment variableへ設定します。ExampleにはPlaceholderだけを収録します。

## Build metadata

Runtime ZIP内の`RELEASE_BUILD.txt`は次を記録します。

```text
package_status=FINAL
application_version=1.17.2
application_label=RSS Reader Modernization 1.17.2
intended_release=1.17.2
intended_tag=v1.17.2
publishable=yes
```

完全Source ZIPは`SOURCE_BUILD.txt`と`SOURCE_MANIFEST.sha256`、Runtime ZIPは`RELEASE_BUILD.txt`と`RELEASE_MANIFEST.sha256`を持ちます。

## 安全境界

Builderはunsafe path、Symlink、Private設定、実DB系拡張子、別ZIP、Python Cache、生成済みRuntime Dataを拒否します。`var/cache/`全体を生成済みRuntime Dataとして除外するため、X APIのCache／connection statusも配布物へ混入しません。VerifierはSHA-256、CRC、重複Path、Absolute / Parent Traversal、Manifest、Version、Secret Patternを確認します。

`final` modeは`APP_VERSION = '1.17.2'`と`APP_VERSION_LABEL = 'RSS Reader Modernization 1.17.2'`が完全一致しない限り実行できません。

## Version 1.17.1からの更新

Version 1.17.2ではDB Migrationはありません。X Timelineを使わない既存環境は新しい必須Secretを設定せず、そのまま他機能を利用出来ます。

X Timelineを利用する場合だけ、Server固有設定へ`APP_X_BEARER_TOKEN`を追加します。Code更新時は既存の`config/local.php`、実DB、`var/`を維持してください。

本番側でPHP CLI、Python、PowerShell等のPatch適用Commandを実行してRuntime fileを生成・書換することは前提にしません。
