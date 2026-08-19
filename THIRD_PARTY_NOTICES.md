# Third-Party Notices

This repository contains vendored third-party frontend assets and one optional runtime-loaded HLS library. The project-level MIT License in `LICENSE` applies to the original RSS Reader application and its modernization work; it does **not** replace or relicense third-party components. Those components remain under their respective upstream licenses.

The distributed third-party source files retain their existing license headers where applicable. Copies of the applicable upstream license notices are stored under `licenses/`. The versions and paths below describe the active dependencies used by the current application.

| Component | Version in this repository | License | Main vendored / runtime paths | License copy |
|---|---:|---|---|---|
| Bootstrap | 5.3.8 | MIT | `public/css/bootstrap-5.3.8.min.css`, `public/js/bootstrap.bundle-5.3.8.min.js` | `licenses/bootstrap-MIT.txt` |
| Bootswatch themes | 5.3.8 | MIT | `public/css/bootstrap-{yeti,minty,flatly,journal,sketchy,solar,slate}-5.3.8.min.css` | `licenses/bootswatch-MIT.txt` |
| jQuery | 3.7.1 | MIT | `public/js/jquery-3.7.1.min.js` | `licenses/jquery-MIT.txt` |
| Popper | bundled with Bootstrap JS | MIT | embedded in `public/js/bootstrap.bundle-5.3.8.min.js`; no standalone runtime file | `licenses/popper-MIT.txt` |
| Font Awesome Free | 6.7.2 | Icons: CC BY 4.0; Fonts: SIL OFL 1.1; Code: MIT | `public/css/all.css`, `public/webfonts/fa-*.ttf`, `public/webfonts/fa-*.woff2` | `licenses/fontawesome-6.7.2-LICENSE.txt` |
| hls.js | 1.6.16 | Apache-2.0 | HLS Widget only: pinned jsDelivr runtime URL with SRI; loaded lazily by `public/js/camera-video-streaming.js` | `licenses/hls.js-1.6.16-Apache-2.0.txt` |

## Upstream references

- Bootstrap 5.3.8: https://github.com/twbs/bootstrap/tree/v5.3.8
- Bootswatch 5.3.8: https://github.com/thomaspark/bootswatch/tree/v5.3.8
- jQuery 3.7.1: https://github.com/jquery/jquery/tree/3.7.1
- Popper v2 documentation: https://popper.js.org/docs/v2/
- Font Awesome Free 6.7.2: https://github.com/FortAwesome/Font-Awesome/tree/6.7.2
- hls.js 1.6.16: https://github.com/video-dev/hls.js/releases/tag/v1.6.16

## Removed from runtime distribution in Version 1.14.0

- Bootstrap / Bootswatch 4.1.3 legacy assets
- jquery-drawer 3.2.2
- iScroll 5.2.0-snapshot
- standalone Popper JavaScript

Their historical license copies may remain under `licenses/` as repository history/documentation, but the corresponding standalone runtime files are not distributed as active Version 1.14.0 assets.

## Notes

- Font Awesomeの配布CSSには6.7.2のLicense / Copyright headerを残している。
- jQueryの配布JavaScriptには3.7.1とOpenJS FoundationのLicense headerを残している。
- Bootswatch theme CSSはGoogle Fontsを外部参照する。Font file自体はこのRepositoryへ同梱していない。
- hls.jsはHLS Widgetが必要な場合だけ`1.6.16`固定URLから読み込み、Subresource Integrityで内容を検証する。hls.jsが利用出来ない場合はNative HLSへFallbackする。
- Brand iconは各商標権者の商標であり、Font Awesome上流のBrand Icons noticeが適用される。

詳細な実ファイル対応は [`docs/dependencies.md`](docs/dependencies.md) を参照してください。
