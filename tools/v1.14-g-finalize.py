#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '1.14.0'
LABEL = 'RSS Reader Modernization 1.14.0'
TAG = 'v1.14.0'
DATE = '2026-08-14'

OLD_BOOTSTRAP_ASSETS = [
    'public/css/bootstrap.min.css',
    'public/css/bootstrap.min.css.map',
    'public/css/bootstrap-yeti.min.css',
    'public/css/bootstrap-minty.min.css',
    'public/css/bootstrap-flatly.min.css',
    'public/css/bootstrap-journal.min.css',
    'public/css/bootstrap-sketchy.min.css',
    'public/css/bootstrap-solar.min.css',
    'public/css/bootstrap-slate.min.css',
    'public/js/bootstrap.min.js',
    'public/js/bootstrap.min.js.map',
]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')


def write(rel: str, text: str) -> None:
    path = ROOT / rel
    path.write_text(text.rstrip() + '\n', encoding='utf-8', newline='\n')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old in text:
        if text.count(old) != 1:
            raise SystemExit(f'{label}: expected one source marker, found {text.count(old)}')
        return text.replace(old, new, 1)
    if new in text:
        return text
    raise SystemExit(f'{label}: source and target markers are both missing')


def update_version() -> None:
    text = read('app/version.php')
    text = replace_once(text, "const APP_VERSION = '1.13.0';", "const APP_VERSION = '1.14.0';", 'APP_VERSION')
    text = replace_once(
        text,
        "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.13.0';",
        "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.14.0';",
        'APP_VERSION_LABEL',
    )
    write('app/version.php', text)


def update_readme() -> None:
    text = read('README.md')
    text = replace_once(text, '**Stable release:** `RSS Reader Modernization 1.13.0`', '**Stable release:** `RSS Reader Modernization 1.14.0`', 'README stable release')
    text = replace_once(text, 'Release tag: `v1.13.0`', 'Release tag: `v1.14.0`', 'README release tag')

    current = (
        'Version 1.14.0では、Frontend dependencyをBootstrap / Bootswatch 5.3.8へ更新し、'
        'Bootstrap 4時代のmarkup / Data APIを5系へ移行しました。右DrawerはBootstrap Offcanvasへ置換し、'
        'jquery-drawer / iScroll / standalone Popperと旧Bootstrap 4配布Assetを削除しています。'
        'PC / Smartphoneと全8 Themeの表示を調整し、Card見出しは`text-bg-*`で背景色に応じた文字色へ自動追従します。'
        'DB schema、Migration、必須configの追加変更はありません。'
    )
    if 'Version 1.14.0では、Frontend dependencyをBootstrap / Bootswatch 5.3.8へ更新' not in text:
        marker = 'Release tag: `v1.14.0`\n\n'
        if marker not in text:
            raise SystemExit('README 1.14 insertion marker is missing')
        text = text.replace(marker, marker + current + '\n\n', 1)

    old_m2 = (
        'Bootstrap / Bootswatchは4.1.3の組合せ、Drawer 3.2.2、iScroll 5.2.0-snapshotを維持し、'
        'Bootstrap 5への移行は別のmajor migrationとして保留しています。'
    )
    new_m2 = (
        'M2時点ではBootstrap / Bootswatch 4.1.3、Drawer 3.2.2、iScroll 5.2.0-snapshotを維持していましたが、'
        'Version 1.14.0でBootstrap / Bootswatch 5.3.8へ移行し、DrawerはBootstrap Offcanvasへ置換、'
        '旧Frontend dependencyは配布物から整理しました。'
    )
    if old_m2 in text:
        text = text.replace(old_m2, new_m2, 1)
    elif new_m2 not in text:
        raise SystemExit('README M2 dependency marker is missing')
    write('README.md', text)


def update_changelog() -> None:
    text = read('CHANGELOG.md')
    heading = f'## RSS Reader Modernization {VERSION} — {DATE}'
    if heading not in text:
        entry = f'''{heading}

### Version 1.14.0 frontend modernization finalization

- Bootstrap / Bootswatchを4.1.3から5.3.8へ更新し、全8 ThemeをVersion固定Assetへ切替。
- Bootstrap 4時代のData API、Form、Utility、Modal等のmarkupをBootstrap 5へ移行。
- 右メニューのjquery-drawerをBootstrap Offcanvasへ置換し、Drawer→Modal遷移時のBackdrop／Focus競合を回避。
- jquery-drawer、iScroll、standalone Popper、および移行完了後のBootstrap 4旧配布Assetを削除。
- PC／Smartphoneと全8 ThemeでNavbar、Modal、Offcanvas、Stock、Memo、Task、Calendar、Mail、Links、Weatherの表示を調整。
- 通常RSS／Search Feed／各WidgetのCard見出しを`text-bg-*`へ統一し、背景色に応じた文字・Icon色へ自動追従。
- Search Feedの見出し背景色を`tr`ではなく`th`へ適用し、Bootstrap 5 Table背景に隠れる問題を修正。
- jQuery 3.7.1、Font Awesome Free 6.7.2、既存API／DB／Widget仕様を維持。
- Version 1.14でDB schema、Migration、SQL、必須configの追加変更はなし。
- Application Versionを`1.14.0`へ確定し、Release package builder／verifierとDependency／Release Documentationを現行構成へ更新。
'''
        marker = '# Changelog\n\n'
        if marker not in text:
            raise SystemExit('CHANGELOG heading marker is missing')
        text = text.replace(marker, marker + entry + '\n', 1)
    write('CHANGELOG.md', text)


def write_release_notes() -> None:
    write('RELEASE_NOTES.md', r'''# RSS Reader Modernization 1.14.0 Release Notes

## Overview

Version 1.14.0は、既存機能・既存データを維持したままFrontend dependencyを現行Bootstrap 5系へ移行するReleaseです。

Bootstrap / Bootswatchを5.3.8へ更新し、Bootstrap 4時代のmarkup / Data APIを5系へ移行しました。右メニューはjquery-drawerからBootstrap Offcanvasへ置換し、移行完了後に不要となった旧Frontend dependencyとBootstrap 4旧配布Assetを削除しています。

jQuery 3.7.1、Font Awesome Free 6.7.2、既存のPHP / PDO / MySQL構成、Feed CRUD、Stock、Search Feed、Memo、Task、Calendar、Mail、Account Settings、Dashboard Widget基盤は維持します。

## Version 1.14 main changes

### Bootstrap / Bootswatch 5.3.8

- Bootstrap 5.3.8 CSSと`bootstrap.bundle-5.3.8.min.js`へruntimeを切替。
- Normal + Yeti / Minty / Flatly / Journal / Sketchy / Solar / Slateの全8 ThemeをBootswatch 5.3.8系へ統一。
- Bootstrap 4時代の`data-toggle` / `data-target`等を`data-bs-*`へ移行。
- Form、Utility、Badge、Modal等のBootstrap 5互換markupへ整理。
- Bootstrap bundleを使用し、standalone Popper runtimeは廃止。

### DrawerからBootstrap Offcanvasへ

- 右メニューをjquery-drawerから`offcanvas-end`へ置換。
- 既存メニュー内容と右側配置を維持。
- Offcanvas表示中はSmartphoneのDashboard swipeを抑止。
- Drawer内ActionからModalを開く場合は、Offcanvasを閉じてからModalを開き、BackdropやScroll lockの競合を回避。
- jquery-drawer、iScroll、standalone Popperを配布物から削除。

### Theme / Responsive finishing

- PC / Smartphoneと全8 ThemeでNavbar、Modal、OffcanvasのcontrastとSpacingを調整。
- Stock、Memo、Task、Calendar、Mail、Links、Weather等の独自surfaceをBootstrap / Bootswatch Theme変数へ追従。
- Solar / Slateを含むDark ThemeでForm label、Calendar、Modal close icon等の視認性を調整。
- SmartphoneのOffcanvas幅とModal footer間隔を調整。

### Card header contrast

- 通常RSS、Search Feed、Clock、Game、Memo、Task、Links、Weather、Calendar、Mailの見出しをBootstrap 5の`text-bg-*`へ統一。
- Card背景色に応じてタイトル、編集／更新Icon、Drag handle等の文字色をTheme側で自動選択。
- Search Feedは背景色をTable rowではなくHeader cellへ適用し、Bootstrap 5 Table背景に隠れる問題を修正。

### Legacy asset cleanup

Version 1.14.0ではruntime参照がないことを確認したうえで、次の旧配布物を削除します。

- jquery-drawer CSS / JavaScript
- iScroll JavaScript
- standalone Popper JavaScript
- Bootstrap 4.1.3の旧CSS / JavaScript / Source Map
- Bootswatch 4.1.3の旧Theme CSS

## Database and configuration

Version 1.14ではDB Table／Column、Migration、SQL、必須configの追加変更はありません。

Version 1.13.0適用済み環境では、DB Migrationを実行せずCodeをVersion 1.14.0へ差し替えます。`config/local.php`、`var/`、実DBはそのまま維持してください。

## Distribution files

- `rss-reader-modernization-1.14.0.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.14.0.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.14.0-complete.zip` — Repository / Testsを含む完全Source成果物。
- `rss-reader-modernization-1.14.0-complete.zip.sha256` — 完全Source ZIPのSHA-256。

Runtime配布物には`config/local.php`、`.env`、実DB、Log、Session、Cache、Release ZIP等を含めません。

## Update notes

更新前にCode、`config/local.php`、実DB、Runtime DataをBackupしてください。

Version 1.13.0から更新する場合は、Server上の旧Frontend Assetを残さないため、`app/`と`public/`をBackup後に入れ替える方法を推奨します。`.htaccess`、`config/`、`var/`、DBは既存環境を維持してください。

Version番号が1.14.0へ変わるためAsset queryも更新されますが、更新直後はBrowserの強制再読込を行うと確実です。

主な確認項目:

- Dashboard / Stock / Settingsが表示できること。
- 右Offcanvasが開閉でき、Drawer内ActionからModalが正常に開くこと。
- 通常RSS / Search Feedの取得、更新、記事Actionが従来どおり動作すること。
- Stock、Memo、Task、Calendar、Mail等の主要Widget操作が従来どおり動作すること。
- Card見出し色変更時に文字とIconが読みやすい色へ追従すること。
- NetworkでBootstrap 5.3.8 bundleが読み込まれ、旧Bootstrap 4 / Drawer / iScroll / standalone Popperが取得されないこと。

## Verification limits

GitHub ActionsではPHP 8.1 / 8.4の`tests/run.sh`全Regression、PHP / JavaScript構文、Bootstrap / Bootswatch 5.3.8 asset checksum、全8 Theme resolver、legacy dependency不存在、Release package builder / verifier、SHA-256 / Manifest整合を確認します。

実Hosting Server、実MySQL Server、外部Feed到達性、実IMAP Server、各Browser / Device固有の描画差については利用環境での最終確認が必要です。

## License

Project LicenseおよびThird-party noticeを維持します。現行Frontend dependencyの詳細は`THIRD_PARTY_NOTICES.md`と`docs/dependencies.md`を参照してください。''')


def write_dependencies() -> None:
    write('docs/dependencies.md', r'''# Frontend dependencies and licenses

Version 1.14.0で配布するFrontend dependencyを、実際のruntime AssetとLicense copyに合わせて整理した一覧です。

| Component | Version | Runtime file | License copy | V1.14確認 |
|---|---:|---|---|---|
| Bootstrap | 5.3.8 | `public/css/bootstrap-5.3.8.min.css`, `public/js/bootstrap.bundle-5.3.8.min.js` | `../licenses/bootstrap-MIT.txt` | Header / checksum確認 |
| Bootswatch | 5.3.8 | 7 theme CSS (`*-5.3.8.min.css`) | `../licenses/bootswatch-MIT.txt` | Yeti / Minty / Flatly / Journal / Sketchy / Solar / Slate確認 |
| jQuery | 3.7.1 full build | `public/js/jquery-3.7.1.min.js` | `../licenses/jquery-MIT.txt` | AJAXを含むfull build |
| Popper | Bootstrap bundle内蔵 | standalone fileなし | `../licenses/popper-MIT.txt` | Bootstrap bundle経由のみ |
| Font Awesome Free | 6.7.2 | `public/css/all.css`, TTF / WOFF2 | `../licenses/fontawesome-6.7.2-LICENSE.txt` | CSS / Font inventory確認 |

## Theme inventory

通常Bootstrapを含め、画面から選択できるThemeは8種類です。

```text
Normal
Yeti
Minty
Flatly
Journal
Sketchy
Solar
Slate
```

Bootswatchとして同梱するのはNormalを除く7 Themeで、すべて5.3.8へ揃えています。

## Removed legacy runtime dependencies

Version 1.14.0ではruntime参照がないことを確認し、次を配布物から削除しました。

- Bootstrap 4.1.3のunversioned CSS / JavaScript / Source Map
- Bootswatch 4.1.3のunversioned Theme CSS
- jquery-drawer 3.2.2
- iScroll 5.2.0-snapshot
- standalone Popper JavaScript

右メニューはBootstrap Offcanvasを使用し、Popperが必要なBootstrap componentは`bootstrap.bundle-5.3.8.min.js`内の実装を使用します。

## License boundary

- Root [`LICENSE`](../LICENSE) はProject独自codeとModernizationで追加・変更した部分のMIT License。
- Vendored libraryはRoot Licenseで再Licenseせず、各LibraryのLicenseを維持する。
- 配布CSS / JavaScript内の上流License headerは削除しない。
- Font AwesomeのIcon / Font / Codeには、それぞれCC BY 4.0 / SIL OFL 1.1 / MITが適用される。
- Brand iconは各権利者の商標であり、同梱だけで推奨・提携を示すものではない。

Repository全体のNoticeは [`../THIRD_PARTY_NOTICES.md`](../THIRD_PARTY_NOTICES.md) を参照してください。''')


def write_notices() -> None:
    write('THIRD_PARTY_NOTICES.md', r'''# Third-Party Notices

This repository contains vendored third-party frontend assets. The project-level MIT License in `LICENSE` applies to the original RSS Reader application and its modernization work; it does **not** replace or relicense third-party components. Those components remain under their respective upstream licenses.

The distributed third-party source files retain their existing license headers. Copies of the applicable upstream license notices are stored under `licenses/`. The versions and paths below are the files distributed by Version 1.14.0.

| Component | Version in this repository | License | Main vendored paths | License copy |
|---|---:|---|---|---|
| Bootstrap | 5.3.8 | MIT | `public/css/bootstrap-5.3.8.min.css`, `public/js/bootstrap.bundle-5.3.8.min.js` | `licenses/bootstrap-MIT.txt` |
| Bootswatch themes | 5.3.8 | MIT | `public/css/bootstrap-{yeti,minty,flatly,journal,sketchy,solar,slate}-5.3.8.min.css` | `licenses/bootswatch-MIT.txt` |
| jQuery | 3.7.1 | MIT | `public/js/jquery-3.7.1.min.js` | `licenses/jquery-MIT.txt` |
| Popper | bundled with Bootstrap JS | MIT | embedded in `public/js/bootstrap.bundle-5.3.8.min.js`; no standalone runtime file | `licenses/popper-MIT.txt` |
| Font Awesome Free | 6.7.2 | Icons: CC BY 4.0; Fonts: SIL OFL 1.1; Code: MIT | `public/css/all.css`, `public/webfonts/fa-*.ttf`, `public/webfonts/fa-*.woff2` | `licenses/fontawesome-6.7.2-LICENSE.txt` |

## Upstream references

- Bootstrap 5.3.8: https://github.com/twbs/bootstrap/tree/v5.3.8
- Bootswatch 5.3.8: https://github.com/thomaspark/bootswatch/tree/v5.3.8
- jQuery 3.7.1: https://github.com/jquery/jquery/tree/3.7.1
- Popper v2 documentation: https://popper.js.org/docs/v2/
- Font Awesome Free 6.7.2: https://github.com/FortAwesome/Font-Awesome/tree/6.7.2

## Removed from runtime distribution in Version 1.14.0

- Bootstrap / Bootswatch 4.1.3 legacy assets
- jquery-drawer 3.2.2
- iScroll 5.2.0-snapshot
- standalone Popper JavaScript

Their historical license copies may remain under`licenses/` as repository history/documentation, but the corresponding standalone runtime files are not distributed as active Version 1.14.0 assets.

## Notes

- Font Awesomeの配布CSSには6.7.2のLicense / Copyright headerを残している。
- jQueryの配布JavaScriptには3.7.1とOpenJS FoundationのLicense headerを残している。
- Bootswatch theme CSSはGoogle Fontsを外部参照する。Font file自体はこのRepositoryへ同梱していない。
- Brand iconは各商標権者の商標であり、Font Awesome上流のBrand Icons noticeが適用される。

詳細な実ファイル対応は [`docs/dependencies.md`](docs/dependencies.md) を参照してください。''')


def write_release_docs() -> None:
    write('docs/versioning.md', r'''# Visible Version Marker

Application Versionと画面表示Labelは`app/version.php`で管理します。

```php
const APP_VERSION = '1.14.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.14.0';
```

- Stable release: `RSS Reader Modernization 1.14.0`
- Git Tag: `v1.14.0`
- Release commit: Version 1.14正式化の最終Commit

正式Releaseでは開発中表記を残さず、Application Version、Label、Runtime ZIP、完全Source ZIP、Release Notes、Tagを同じVersionへ揃えます。

過去のSB、M1、M2、M4、V1.x工程表記は履歴Document内に残しますが、現在Versionを示す入口には使用しません。''')

    write('docs/release-package.md', r'''# Version 1.14.0 Release package

## Package種類

| Artifact | 用途 | Tests / .github |
|---|---|---|
| `rss-reader-modernization-1.14.0-complete.zip` | GitHub作業Folder相当の完全Source成果物 | 含む |
| `rss-reader-modernization-1.14.0.zip` | Server配置用Runtime成果物 | 含まない |

両方とも固定Timestamp・Path順で生成し、同一Sourceから同じSHA-256になるDeterministic Buildとします。

## 生成

```bash
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
```

## 検証

```bash
python tools/verify_complete_package.py \
  ../release-output/rss-reader-modernization-1.14.0-complete.zip \
  ../release-output/rss-reader-modernization-1.14.0-complete.zip.sha256

python tools/verify_release_package.py \
  ../release-output/rss-reader-modernization-1.14.0.zip \
  ../release-output/rss-reader-modernization-1.14.0.zip.sha256
```

## Runtime ZIPへ含める

Application、Public Asset、設定Example、Schema / Migration / Audit SQL、運用Tool、License、Release Notes、設置・更新・復旧Document、空のRuntime Directoryを含めます。

## Runtime ZIPへ含めない

- `tests/`、`.github/`、Git作業用Checklist
- `config/local.php`、`.env`、秘密鍵、Token
- 実DB、Dump、Backup、Log、Session、Cache、Throttle Data
- Legacy ZIP、別Release ZIP、Python Cache

## Build metadata

Runtime ZIP内の`RELEASE_BUILD.txt`は次を記録します。

```text
package_status=FINAL
application_version=1.14.0
application_label=RSS Reader Modernization 1.14.0
intended_release=1.14.0
intended_tag=v1.14.0
publishable=yes
```

完全Source ZIPは`SOURCE_BUILD.txt`と`SOURCE_MANIFEST.sha256`、Runtime ZIPは`RELEASE_BUILD.txt`と`RELEASE_MANIFEST.sha256`を持ちます。

## 安全境界

Builderはunsafe path、Symlink、Private設定、実DB系拡張子、別ZIP、Python Cache、生成済みRuntime Dataを拒否します。VerifierはSHA-256、CRC、重複Path、Absolute / Parent Traversal、Manifest、Version、Secret Patternを確認します。

`final` modeは`APP_VERSION = '1.14.0'`と`APP_VERSION_LABEL = 'RSS Reader Modernization 1.14.0'`が完全一致しない限り実行できません。''')

    write('docs/tag-and-github-release.md', r'''# Git Tag / GitHub Release手順

GitHubへの正式反映は、Release Gateと成果物確認後に実行します。

## 1. 差分とTest確認

```bash
git status --short
git diff --check
bash tests/run.sh
```

`config/local.php`、実DB、Log、Session、Cache、Throttle Data、Release ZIPそのものが意図せずStage対象になっていないことを確認します。

## 2. Release Artifact生成

```bash
python tools/build_complete_package.py --output-dir ../release-output
python tools/build_release_package.py --mode final --output-dir ../release-output
python tools/verify_complete_package.py \
  ../release-output/rss-reader-modernization-1.14.0-complete.zip \
  ../release-output/rss-reader-modernization-1.14.0-complete.zip.sha256
python tools/verify_release_package.py \
  ../release-output/rss-reader-modernization-1.14.0.zip \
  ../release-output/rss-reader-modernization-1.14.0.zip.sha256
```

## 3. Commit / Push

```bash
git add -A
git status --short
git diff --cached --check
git commit -m "release: finalize version 1.14.0"
git push origin main
```

## 4. Annotated Tag

```bash
git status --short
git log -1 --oneline
git tag -a v1.14.0 -m "RSS Reader Modernization 1.14.0"
git show --no-patch --decorate v1.14.0
git push origin v1.14.0
```

TagはRelease commitと同じCommitを指すことを確認します。

## 5. GitHub Release

- Tag: `v1.14.0`
- Title: `RSS Reader Modernization 1.14.0`
- 本文: `RELEASE_NOTES.md`
- 添付:
  - `rss-reader-modernization-1.14.0.zip`
  - `rss-reader-modernization-1.14.0.zip.sha256`
  - 必要に応じて`rss-reader-modernization-1.14.0-complete.zip`とSHA-256
- Pre-release: OFF
- Latest release: ON

## 6. 公開後確認

- main、Tag、GitHub ReleaseのVersionが1.14.0で一致する。
- SHA-256が手元のArtifactと一致する。
- Runtime ZIPへPrivate設定やRuntime Dataがない。
- 展開後Footerが`RSS Reader Modernization 1.14.0`。
- GitHub ActionsがPASSする。''')


def update_release_tools() -> None:
    paths = [
        'tools/build_release_package.py',
        'tools/verify_release_package.py',
        'tools/build_complete_package.py',
        'tools/verify_complete_package.py',
    ]
    for rel in paths:
        text = read(rel)
        text = text.replace('1\\.12\\.0', '1\\.14\\.0')
        text = text.replace('1.12.0', '1.14.0')
        write(rel, text)


def remove_old_bootstrap_assets() -> None:
    for rel in OLD_BOOTSTRAP_ASSETS:
        path = ROOT / rel
        if path.exists():
            path.unlink()


def main() -> None:
    update_version()
    update_readme()
    update_changelog()
    write_release_notes()
    write_dependencies()
    write_notices()
    write_release_docs()
    update_release_tools()
    remove_old_bootstrap_assets()
    print(f'V1.14-G finalization applied for {VERSION} / {TAG}.')


if __name__ == '__main__':
    main()
