# Frontend dependencies and licenses

M4-B時点で配布するFrontend dependencyを、実際のAssetとLicense copyに合わせて整理した一覧です。M4-BではLibrary本体の更新は行っていません。

| Component | Version | Runtime file | License copy | M4-B確認 |
|---|---:|---|---|---|
| Bootstrap | 4.1.3 | `public/css/bootstrap.min.css`, `public/js/bootstrap.min.js` | `../licenses/bootstrap-MIT.txt` | HeaderとVersionを確認 |
| Bootswatch | 4.1.3 | 7 theme CSS | `../licenses/bootswatch-MIT.txt` | Yeti / Minty / Flatly / Journal / Sketchy / Solar / Slateを確認 |
| jQuery | 3.7.1 full build | `public/js/jquery-3.7.1.min.js` | `../licenses/jquery-MIT.txt` | AJAXを含むfull build、OpenJS Foundation noticeを確認 |
| Popper.js | 1.x vendored build | `public/js/popper.min.js` | `../licenses/popper-MIT.txt` | Bootstrap 4互換の現行Assetを維持 |
| jquery-drawer | 3.2.2 | `public/css/drawer.min.css`, `public/js/drawer.min.js` | `../licenses/jquery-drawer-MIT.txt` | Headerを確認 |
| iScroll | 5.2.0-snapshot | `public/js/iscroll.js` | `../licenses/iscroll-MIT.txt` | Headerを確認 |
| Font Awesome Free | 6.7.2 | `public/css/all.css`, TTF / WOFF2 8 files | `../licenses/fontawesome-6.7.2-LICENSE.txt` | CSS Header、Font inventory、Licenseを確認 |

## Theme inventory

通常のBootstrapを含め、画面から選択できる状態は8種類です。

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

Bootswatchとして同梱するのはNormalを除く7 themeです。

## License boundary

- Root [`LICENSE`](../LICENSE) はProject独自codeとModernizationで追加・変更した部分のMIT License。
- Vendored libraryはRoot Licenseで再Licenseせず、各LibraryのLicenseを維持する。
- 配布CSS / JavaScript内の上流License headerは削除しない。
- Font AwesomeのIcon / Font / Codeには、それぞれCC BY 4.0 / SIL OFL 1.1 / MITが適用される。
- Brand iconは各権利者の商標であり、同梱だけで推奨・提携を示すものではない。

Repository全体のNoticeは [`../THIRD_PARTY_NOTICES.md`](../THIRD_PARTY_NOTICES.md) を参照してください。

## M4-Bで変更していないもの

Bootstrap、Bootswatch、Popper、Drawer、iScroll、jQuery、Font AwesomeのRuntime binary / CSSは変更していません。M4-Bで行ったのはDocumentation、Notice、License copyの整合だけです。
