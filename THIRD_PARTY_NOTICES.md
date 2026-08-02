# Third-Party Notices

This repository contains vendored third-party frontend assets. The project-level
MIT License in `LICENSE` applies to the original RSS Reader application and its
modernization work; it does **not** replace or relicense third-party components.
Those components remain under their respective upstream licenses.

The distributed third-party source files retain their existing license headers.
Copies of the applicable upstream license notices are stored under `licenses/`.
The versions and paths below are the files distributed by this checkpoint.

| Component | Version in this repository | License | Main vendored paths | License copy |
|---|---:|---|---|---|
| Bootstrap | 4.1.3 | MIT | `public/css/bootstrap.min.css`, `public/js/bootstrap.min.js` | `licenses/bootstrap-MIT.txt` |
| Bootswatch themes | 4.1.3 | MIT | `public/css/bootstrap-{yeti,minty,flatly,journal,sketchy,solar,slate}.min.css` | `licenses/bootswatch-MIT.txt` |
| jQuery | 3.7.1 | MIT | `public/js/jquery-3.7.1.min.js` | `licenses/jquery-MIT.txt` |
| Popper.js | 1.x vendored build | MIT | `public/js/popper.min.js` | `licenses/popper-MIT.txt` |
| jquery-drawer | 3.2.2 | MIT | `public/css/drawer.min.css`, `public/js/drawer.min.js` | `licenses/jquery-drawer-MIT.txt` |
| iScroll | 5.2.0-snapshot | MIT | `public/js/iscroll.js` | `licenses/iscroll-MIT.txt` |
| Font Awesome Free | 6.7.2 | Icons: CC BY 4.0; Fonts: SIL OFL 1.1; Code: MIT | `public/css/all.css`, `public/webfonts/fa-*.ttf`, `public/webfonts/fa-*.woff2` | `licenses/fontawesome-6.7.2-LICENSE.txt` |

## Upstream references

- Bootstrap 4.1.3: https://github.com/twbs/bootstrap/tree/v4.1.3
- Bootswatch 4.1.3: https://github.com/thomaspark/bootswatch/tree/v4.1.3
- jQuery 3.7.1: https://github.com/jquery/jquery/tree/3.7.1
- Popper.js 1.x: https://github.com/FezVrasta/popper.js
- jquery-drawer 3.2.2: https://github.com/blivesta/drawer/tree/v3.2.2
- iScroll 5.2.0-snapshot: https://github.com/cubiq/iscroll
- Font Awesome Free 6.7.2: https://github.com/FortAwesome/Font-Awesome/tree/6.7.2

## Notes

- Font Awesomeの配布CSSには6.7.2のLicense / Copyright headerを残している。
- jQueryの配布JavaScriptには3.7.1とOpenJS FoundationのLicense headerを残している。
- Bootswatch theme CSSはGoogle Fontsを外部参照する。Font file自体はこのRepositoryへ同梱していない。
- Brand iconは各商標権者の商標であり、Font Awesome上流のBrand Icons noticeが適用される。
- Source Mapは配布Assetの一部として残すが、License一覧では対応するBootstrap本体へ含めて扱う。

詳細な実ファイル対応は [`docs/dependencies.md`](docs/dependencies.md) を参照してください。
