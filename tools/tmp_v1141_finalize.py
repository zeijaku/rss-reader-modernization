from pathlib import Path


def replace_once(path: str, old: str, new: str, label: str) -> None:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected 1 occurrence, found {count}')
    p.write_text(text.replace(old, new, 1), encoding='utf-8')


replace_once('app/version.php', "const APP_VERSION = '1.14.0';", "const APP_VERSION = '1.14.1';", 'APP_VERSION')
replace_once('app/version.php', "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.14.0';", "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.14.1';", 'APP_VERSION_LABEL')

replace_once(
    'README.md',
    '**Stable release:** `RSS Reader Modernization 1.14.0`\nRelease tag: `v1.14.0`',
    '**Stable release:** `RSS Reader Modernization 1.14.1`\nRelease tag: `v1.14.1`',
    'README stable release',
)
readme = Path('README.md')
text = readme.read_text(encoding='utf-8')
anchor = 'Version 1.14.0では、Frontend dependencyをBootstrap / Bootswatch 5.3.8へ更新し、'
if anchor not in text:
    raise SystemExit('README 1.14.0 anchor missing')
paragraph = (
    'Version 1.14.1では、Bootstrap / Bootswatch Themeに合わせて通常RSS／Search Feed、Task、Stock、Calendar、Mail、Links、Weather、Clock、Mini Game等の中立Surface・本文色・補助色をTheme変数へ追従させました。Keyword Highlight、休日、Timer終了、Game状態色など意味を持つ色は従来仕様を維持しています。DB schema、Migration、必須configの変更はありません。\n\n'
)
readme.write_text(text.replace(anchor, paragraph + anchor, 1), encoding='utf-8')

changelog = Path('CHANGELOG.md')
text = changelog.read_text(encoding='utf-8')
marker = '# Changelog\n\n'
if not text.startswith(marker):
    raise SystemExit('CHANGELOG header missing')
entry = """## RSS Reader Modernization 1.14.1 — 2026-08-15

### Bootstrap / Bootswatch Theme alignment

- 通常RSS／Search Feedの記事Titleと概要を`--bs-body-color`／`--bs-body-bg`へ追従させ、Solar／Slate等のDark Themeで本文が同化する問題を修正。
- 記事Actions、Task、Stock、Calendar、Mail、Links、Weather、Clock Timer、Mini Game／Lights Outの中立Surface・補助色・BorderをBootstrap 5 Theme変数へ整理。
- Stock Tag管理PanelやSmartphoneのRSS概要Iconなど、後勝ちしていた固定色もTheme連動へ修正。
- Keyword Highlight、休日／週末、Timer終了、GameのPlayer／敵／宝／Goal、Lights Out ON等の意味を持つ状態色は明示色を維持。
- Solar／Slate専用の中立色上書きを減らし、Bootstrap / Bootswatch 5.3.8のTheme変数を共通契約として利用。
- PHP、JavaScript、HTML、API、DB schema、Migration、必須configの変更はなし。
- Application Versionを`1.14.1`へ更新。

"""
changelog.write_text(marker + entry + text[len(marker):], encoding='utf-8')

Path('RELEASE_NOTES.md').write_text("""# RSS Reader Modernization 1.14.1 Release Notes

## Overview

Version 1.14.1は、Version 1.14.0のBootstrap / Bootswatch 5.3.8移行後に確認したTheme表示の補正Releaseです。機能追加やDB変更は行わず、独自UIに残っていた固定色をBootstrap 5 Theme変数へ追従させます。

## Main changes

- 通常RSS／Search Feedの記事Title・概要の本文色と背景をThemeへ追従。
- 記事Actions、Task、Stock、Calendar、Mail、Links、Weather、Clock Timerの中立Surface・本文色・補助色・BorderをTheme変数へ整理。
- Stock Tag管理PanelとSmartphoneのRSS概要IconをTheme連動へ修正。
- Mini Game／Lights Outは通常Surface、空セル、壁、補助文字、OFF状態のみTheme変数へ寄せ、ゲーム上の意味を持つ色は維持。
- Keyword Highlightの黄色＋暗色文字、休日／週末、Timer終了、Player／敵／宝／Goal、Lights Out ON、Swipe Indicator、常時LightのOffcanvas Drawer等は意味・視認性を優先して従来色を維持。

## Database and configuration

DB Table／Column、Migration、SQL、必須configの追加変更はありません。Version 1.14.0適用済み環境ではCode差し替えのみです。

## Distribution files

- `rss-reader-modernization-1.14.1.zip` — Server配置用Runtime成果物。
- `rss-reader-modernization-1.14.1.zip.sha256` — Runtime ZIPのSHA-256。
- `rss-reader-modernization-1.14.1-complete.zip` — Repository / Testsを含む完全Source成果物。
- `rss-reader-modernization-1.14.1-complete.zip.sha256` — 完全Source ZIPのSHA-256。

Runtime配布物には`config/local.php`、`.env`、実DB、Log、Session、Cache、Release ZIP等を含めません。

## Update notes

Version 1.14.0からはDB Migration不要です。更新前にCodeをBackupし、配布物の`app/`と`public/`を反映してください。`config/`、`var/`、DB、Server固有の`.htaccess`は既存環境を維持します。Version番号更新によりAsset queryも変わりますが、更新直後はBrowserの強制再読込を推奨します。

主な確認項目:

- Normal系ThemeとSolar／Slate等Dark ThemeでRSS本文が読めること。
- Task、Stock Tag管理、Mail、Clock Timer、Mini Game等の中立SurfaceがThemeに馴染むこと。
- Keyword Highlight、休日、Timer終了、Game状態色等の意味色が従来どおり識別できること。
- Dashboard / Stock / Settings / Offcanvas / Modal等の既存操作が維持されること。

## Verification limits

GitHub ActionsではPHP 8.1 / 8.4の`tests/run.sh`全RegressionとRelease package builder / verifierを確認します。実Hosting Server、実MySQL、外部Feed、IMAP、各Browser / Device固有の描画差は利用環境での最終確認が必要です。

## License

Project LicenseおよびThird-party noticeを維持します。Frontend dependencyはVersion 1.14.0と同じBootstrap / Bootswatch 5.3.8、jQuery 3.7.1、Font Awesome Free 6.7.2です。
""", encoding='utf-8')

for path in [
    'tools/build_release_package.py',
    'tools/build_complete_package.py',
    'tools/verify_release_package.py',
    'tools/verify_complete_package.py',
    'docs/release-package.md',
    'docs/tag-and-github-release.md',
    'docs/versioning.md',
]:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    if '1.14.0' not in text:
        raise SystemExit(f'{path}: expected 1.14.0 marker missing')
    p.write_text(text.replace('v1.14.0', 'v1.14.1').replace('1.14.0', '1.14.1'), encoding='utf-8')

replace_once(
    'tests/test_v15b_architecture.py',
    "check('bootstrap-solar' in timer_css and 'bootstrap-slate' in timer_css, 'existing dark Themes have Timer surface adjustments')",
    "check('var(--bs-body-color' in timer_css and 'var(--bs-secondary-color' in timer_css and 'bootstrap-solar' not in timer_css and 'bootstrap-slate' not in timer_css, 'Timer neutral surfaces follow active Bootstrap / Bootswatch Theme variables')",
    'V1.5-B Timer Theme contract',
)
